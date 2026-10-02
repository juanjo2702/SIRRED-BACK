<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\Corte;
use App\Models\Docente;
use App\Models\Facturacion;
use App\Models\FacturacionLog;
use App\Models\Sede;
use App\Models\SedeCarrera;
use App\Models\User;
use App\Models\Role;
use App\Services\FacturacionUpdateService;
use App\Services\FacturacionUploadService;
use App\Services\FacturacionPrintPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacturacionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test admin can upload to a closed corte, but public cannot.
     */
    public function test_upload_to_closed_corte(): void
    {
        // 1. Arrange
        $role = Role::firstOrCreate(['nombre' => 'admin']);
        $user = User::create([
            'name' => 'Jenny',
            'apellidos' => 'Garcia',
            'ci' => '5927724_admin1',
            'role_id' => $role->id,
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);

        $corte = Corte::create([
            'nombre' => 'Corte 2026-I',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
            'estado' => 0, // Closed!
            'tipo_corte' => 'FACTURACION',
        ]);

        $sede = Sede::create([
            'nombre' => 'Cochabamba',
            'estado' => 1,
            'abreviacion' => 'CBBA',
        ]);

        $carrera = Carrera::create([
            'nombre' => 'Medicina',
            'estado' => 1,
        ]);

        $sedeCarrera = SedeCarrera::create([
            'sede_id' => $sede->id,
            'carrera_id' => $carrera->id,
            'estado' => 1,
        ]);

        $docente = Docente::create([
            'nombre' => 'Jenny',
            'apellidos' => 'Garcia Morales',
            'ci' => '5927724',
            'estado' => 1,
        ]);

        $facturacion = Facturacion::create([
            'docente_id' => $docente->id,
            'sede_carrera_id' => $sedeCarrera->id,
            'corte_id' => $corte->id,
            'tipo_contrato' => 'FACTURACION',
            'monto' => 1500.00,
            'carga_horaria' => 40,
            'estado_subida' => 'PENDIENTE',
        ]);

        $file = UploadedFile::fake()->create('invoice.pdf', 500, 'application/pdf');

        // 2. Act & Assert: Public/Non-Admin upload should fail
        $uploadService = app(FacturacionUploadService::class);

        try {
            $uploadService->upload($facturacion, $file, false, 'Portal publico');
            $this->fail('Se esperaba que fallara la subida en un corte cerrado para usuarios no admins.');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('El corte administrativo está cerrado. No se admiten cargas.', $e->getMessage());
        }

        // 3. Act & Assert: Admin upload should succeed
        $result = $uploadService->upload($facturacion, $file, true, 'Subida especial por admin');
        
        $expectedPath = 'facturas/5927724_CBBA_Medicina_Corte2026-I.pdf';
        $this->assertEquals($expectedPath, $result->factura_path);
        $this->assertEquals('SUBIDA', $result->estado_subida);
        
        Storage::disk('public')->assertExists($expectedPath);

        // Assert Audit Log was created
        $this->assertDatabaseHas('facturacion_logs', [
            'facturacion_id' => $facturacion->id,
            'user_id' => $user->id,
            'action' => 'ADMIN_UPLOAD_FACTURA_CLOSED_CORTE',
            'comment' => 'Subida especial por admin',
        ]);
    }

    /**
     * Test comprehensive updates to docente details and auto physical PDF renaming.
     */
    public function test_comprehensive_update_and_auto_pdf_renaming(): void
    {
        // Arrange
        $role = Role::firstOrCreate(['nombre' => 'admin']);
        $user = User::create([
            'name' => 'Jenny',
            'apellidos' => 'Garcia',
            'ci' => '5927724_admin2',
            'role_id' => $role->id,
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);

        $corte = Corte::create([
            'nombre' => 'Corte 2026-I',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
            'estado' => 1,
            'tipo_corte' => 'FACTURACION',
        ]);

        $sede = Sede::create([
            'nombre' => 'Cochabamba',
            'estado' => 1,
            'abreviacion' => 'CBBA',
        ]);

        $carrera = Carrera::create([
            'nombre' => 'Medicina',
            'estado' => 1,
        ]);

        $sedeCarrera = SedeCarrera::create([
            'sede_id' => $sede->id,
            'carrera_id' => $carrera->id,
            'estado' => 1,
        ]);

        $docente = Docente::create([
            'nombre' => 'Jenny',
            'apellidos' => 'Garcia Morales',
            'ci' => '5927724',
            'estado' => 1,
        ]);

        $initialPath = 'facturas/5927724_CBBA_Medicina_Corte2026-I.pdf';
        $facturacion = Facturacion::create([
            'docente_id' => $docente->id,
            'sede_carrera_id' => $sedeCarrera->id,
            'corte_id' => $corte->id,
            'tipo_contrato' => 'FACTURACION',
            'monto' => 1500.00,
            'carga_horaria' => 40,
            'estado_subida' => 'SUBIDA',
            'factura_path' => $initialPath,
        ]);

        // Place a mock physical file on disk
        Storage::disk('public')->put($initialPath, 'dummy content');

        // New Sede / Carrera
        $newSede = Sede::create([
            'nombre' => 'La Paz',
            'estado' => 1,
            'abreviacion' => 'LPZ',
        ]);
        
        $newCarrera = Carrera::create([
            'nombre' => 'Odontologia',
            'estado' => 1,
        ]);

        $newSedeCarrera = SedeCarrera::create([
            'sede_id' => $newSede->id,
            'carrera_id' => $newCarrera->id,
            'estado' => 1,
        ]);

        // Act: Update CI, names, and career/sede
        $updateService = app(FacturacionUpdateService::class);
        $updateData = [
            'ci' => '1234567',
            'nombres' => 'Jenny Patricia',
            'apellidos' => 'Garcia M',
            'correo' => 'jenny@unitepc.edu',
            'telefono' => '77223344',
            'complemento' => '1B',
            'sede_id' => $newSede->id,
            'carrera_id' => $newCarrera->id,
            'monto' => 2000.00,
            'observaciones' => 'Monto ajustado y CI corregido',
        ];

        $result = $updateService->update($facturacion, $updateData, true, 'Correccion administrativa integral');

        // Assert: Database has new values
        $this->assertEquals('1234567', $result->docente->ci);
        $this->assertEquals('Jenny Patricia', $result->docente->nombre);
        $this->assertEquals('jenny@unitepc.edu', $result->docente->correo);
        $this->assertEquals('77223344', $result->docente->telefono);
        $this->assertEquals('1B', $result->docente->complemento);
        $this->assertEquals(2000.00, $result->monto);
        $this->assertEquals('Monto ajustado y CI corregido', $result->observaciones);
        $this->assertEquals($newSedeCarrera->id, $result->sede_carrera_id);

        // Assert: Physical renaming happened successfully
        $expectedNewPath = 'facturas/1234567_LPZ_Odontologia_Corte2026-I.pdf';
        $this->assertEquals($expectedNewPath, $result->factura_path);
        
        Storage::disk('public')->assertMissing($initialPath);
        Storage::disk('public')->assertExists($expectedNewPath);

        // Assert: Audit log captured details
        $this->assertDatabaseHas('facturacion_logs', [
            'facturacion_id' => $facturacion->id,
            'user_id' => $user->id,
            'action' => 'UPDATE_FACTURACION_DATA',
            'comment' => 'Correccion administrativa integral',
        ]);
    }

    /**
     * Test approved invoice locks and admin force bypass.
     */
    public function test_approved_invoice_locks_and_bypass(): void
    {
        $role = Role::firstOrCreate(['nombre' => 'admin']);
        $user = User::create([
            'name' => 'Jenny',
            'apellidos' => 'Garcia',
            'ci' => '5927724_admin3',
            'role_id' => $role->id,
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);

        $corte = Corte::create([
            'nombre' => 'Corte 2026-I',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
            'estado' => 1,
            'tipo_corte' => 'FACTURACION',
        ]);

        $sede = Sede::create([
            'nombre' => 'Cochabamba',
            'estado' => 1,
            'abreviacion' => 'CBBA',
        ]);

        $carrera = Carrera::create([
            'nombre' => 'Medicina',
            'estado' => 1,
        ]);

        $sedeCarrera = SedeCarrera::create([
            'sede_id' => $sede->id,
            'carrera_id' => $carrera->id,
            'estado' => 1,
        ]);

        $docente = Docente::create([
            'nombre' => 'Jenny',
            'apellidos' => 'Garcia Morales',
            'ci' => '5927724',
            'estado' => 1,
        ]);

        $facturacion = Facturacion::create([
            'docente_id' => $docente->id,
            'sede_carrera_id' => $sedeCarrera->id,
            'corte_id' => $corte->id,
            'tipo_contrato' => 'FACTURACION',
            'monto' => 1500.00,
            'carga_horaria' => 40,
            'estado_subida' => 'APROBADO', // Approved & Locked!
        ]);

        $updateService = app(FacturacionUpdateService::class);

        // 1. Try to update normally -> should fail
        try {
            $updateService->update($facturacion, ['monto' => 1800.00], true, 'Regular update', false);
            $this->fail('Se esperaba excepcion al intentar editar un registro APROBADO sin forzar.');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('El registro de facturación está APROBADO y bloqueado. Use confirmación especial para forzar.', $e->getMessage());
        }

        // 2. Update with force -> should succeed
        $result = $updateService->update($facturacion, ['monto' => 1800.00], true, 'Forzar edicion aprobada', true);
        
        $this->assertEquals(1800.00, $result->monto);
        
        $this->assertDatabaseHas('facturacion_logs', [
            'facturacion_id' => $facturacion->id,
            'user_id' => $user->id,
            'action' => 'UPDATE_FACTURACION_DATA',
            'comment' => 'Forzar edicion aprobada',
        ]);
    }

    /**
     * Test generating consolidated PDF package.
     */
    public function test_generate_pdf_consolidated_package(): void
    {
        $role = Role::firstOrCreate(['nombre' => 'admin']);
        $user = User::create([
            'name' => 'Jenny',
            'apellidos' => 'Garcia',
            'ci' => '5927724_admin4',
            'role_id' => $role->id,
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);

        $corte = Corte::create([
            'nombre' => 'Corte 2026-I',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
            'estado' => 1,
            'tipo_corte' => 'FACTURACION',
        ]);

        $sede = Sede::create([
            'nombre' => 'Cochabamba',
            'estado' => 1,
            'abreviacion' => 'CBBA',
        ]);

        $carrera = Carrera::create([
            'nombre' => 'Medicina',
            'estado' => 1,
        ]);

        $sedeCarrera = SedeCarrera::create([
            'sede_id' => $sede->id,
            'carrera_id' => $carrera->id,
            'estado' => 1,
        ]);

        $docente = Docente::create([
            'nombre' => 'Jenny',
            'apellidos' => 'Garcia Morales',
            'ci' => '5927724',
            'estado' => 1,
        ]);

        Facturacion::create([
            'docente_id' => $docente->id,
            'sede_carrera_id' => $sedeCarrera->id,
            'corte_id' => $corte->id,
            'tipo_contrato' => 'FACTURACION',
            'monto' => 1500.00,
            'carga_horaria' => 40,
            'estado_subida' => 'PENDIENTE',
        ]);

        $printService = app(FacturacionPrintPackageService::class);
        
        $pdfContent = $printService->generatePackage([
            'corte_id' => $corte->id,
            'sede_id' => $sede->id,
            'carrera_id' => $carrera->id,
        ]);

        // Verify we got a PDF stream (starts with %PDF)
        $this->assertStringStartsWith('%PDF-', $pdfContent);

        // Verify action log is stored
        $this->assertDatabaseHas('facturacion_logs', [
            'action' => 'GENERATE_PRINT_PACKAGE',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test admin can delete/destroy a facturacion record.
     */
    public function test_admin_can_delete_facturacion_record(): void
    {
        $role = Role::firstOrCreate(['nombre' => 'admin']);
        $user = User::create([
            'name' => 'Jenny',
            'apellidos' => 'Garcia',
            'ci' => '5927724_admin5',
            'role_id' => $role->id,
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);

        $corte = Corte::create([
            'nombre' => 'Corte 2026-I',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
            'estado' => 1,
            'tipo_corte' => 'FACTURACION',
        ]);

        $sede = Sede::create([
            'nombre' => 'Cochabamba',
            'estado' => 1,
            'abreviacion' => 'CBBA',
        ]);

        $carrera = Carrera::create([
            'nombre' => 'Medicina',
            'estado' => 1,
        ]);

        $sedeCarrera = SedeCarrera::create([
            'sede_id' => $sede->id,
            'carrera_id' => $carrera->id,
            'estado' => 1,
        ]);

        $docente = Docente::create([
            'nombre' => 'Jenny',
            'apellidos' => 'Garcia Morales',
            'ci' => '5927724',
            'estado' => 1,
        ]);

        $facturacion = Facturacion::create([
            'docente_id' => $docente->id,
            'sede_carrera_id' => $sedeCarrera->id,
            'corte_id' => $corte->id,
            'tipo_contrato' => 'FACTURACION',
            'monto' => 1500.00,
            'carga_horaria' => 40,
            'estado_subida' => 'PENDIENTE',
            'factura_path' => 'facturas/test.pdf',
        ]);

        // Place a mock physical file on disk
        Storage::disk('public')->put('facturas/test.pdf', 'dummy content');

        // Execute DELETE request to /api/facturaciones/{id}
        $response = $this->deleteJson("/api/facturaciones/{$facturacion->id}");

        // Assert response is successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Registro de facturación eliminado correctamente.']);

        // Assert record is deleted from DB
        $this->assertDatabaseMissing('facturacions', ['id' => $facturacion->id]);

        // Assert physical file is deleted
        Storage::disk('public')->assertMissing('facturas/test.pdf');

        // Assert Audit Log was created
        $this->assertDatabaseHas('facturacion_logs', [
            'facturacion_id' => null,
            'user_id' => $user->id,
            'action' => 'DELETE_FACTURACION_RECORD',
        ]);
    }
}
