<?php

use App\Filament\Pages\Contact;
use App\Filament\Pages\General;
use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\DocumentLayouts\DocumentLayoutResource;
use App\Filament\Resources\DocumentLayouts\Pages\CreateDocumentLayout;
use App\Filament\Resources\DocumentLayouts\Pages\ListDocumentLayouts;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use App\Filament\Resources\DocumentTemplates\Pages\CreateDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\ListDocumentTemplates;
use App\Filament\Resources\PdfDocuments\Pages\CreatePdfDocument;
use App\Filament\Resources\PdfDocuments\Pages\EditPdfDocument;
use App\Filament\Resources\PdfDocuments\Pages\ListPdfDocuments;
use App\Filament\Resources\PdfDocuments\PdfDocumentResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Admin;
use App\Models\Document;
use App\Models\DocumentLayout;
use App\Models\DocumentTemplate;
use App\Models\PdfDocument;
use App\Models\User;
use App\Services\DocumentPdf;
use App\Settings\GeneralSettings;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Jacobtims\FilamentLogger\Resources\ActivityResource\Pages\ListActivities;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * Mirrors scripts/migrate.sh: Shield generates the permissions and super_admin gets all of them
 * (super_admin is a plain role here, not a gate bypass).
 */
function superAdmin(array $attributes = []): Admin
{
    test()->artisan('shield:generate', ['--all' => true, '--option' => 'permissions', '--panel' => 'admin'])
        ->assertSuccessful();

    $role = Role::findOrCreate('super_admin', 'admin');
    $role->syncPermissions(Permission::where('guard_name', 'admin')->get());

    $admin = Admin::factory()->create($attributes);
    $admin->assignRole($role);

    return $admin;
}

test('guests are redirected to the admin login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('the admin login page renders', function () {
    $this->get('/admin/login')->assertOk()->assertSee('Sign in');
});

test('admins can log in with valid credentials', function () {
    $admin = superAdmin();

    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'Admin@123'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    $this->assertAuthenticatedAs($admin, 'admin');
});

test('admins cannot log in with an invalid password', function () {
    $admin = superAdmin();

    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'wrong-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest('admin');
});

test('super admins can open every panel page', function (string $uri) {
    $this->actingAs(superAdmin(), 'admin')
        ->get($uri)
        ->assertOk();
})->with([
    'dashboard' => '/admin',
    'admins' => '/admin/admins',
    'create admin' => '/admin/admins/create',
    'users' => '/admin/users',
    'create user' => '/admin/users/create',
    'roles' => '/admin/shield/roles',
    'create role' => '/admin/shield/roles/create',
    'general settings' => '/admin/general',
    'contact settings' => '/admin/contact',
    'activity logs' => '/admin/activity-logs',
    'log viewer' => '/admin/logs',
    'documents' => '/admin/documents',
    'create document' => '/admin/documents/create',
    'templates' => '/admin/document-templates',
    'create template' => '/admin/document-templates/create',
    'layouts' => '/admin/document-layouts',
    'create layout' => '/admin/document-layouts/create',
]);

test('the sidebar shows the new documents and hides the old documents and letterheads', function () {
    expect(PdfDocumentResource::shouldRegisterNavigation())->toBeTrue()
        ->and(DocumentTemplateResource::shouldRegisterNavigation())->toBeTrue()
        ->and(DocumentResource::shouldRegisterNavigation())->toBeFalse()
        ->and(DocumentLayoutResource::shouldRegisterNavigation())->toBeFalse();

    $this->actingAs(superAdmin(), 'admin');

    $this->get('/admin/pdf-documents')->assertOk();
    $this->get('/admin/pdf-documents/create')->assertOk();
});

test('a new document saves its images, previews, finalizes and locks', function () {
    Storage::fake('local');
    $this->actingAs(superAdmin(), 'admin');

    Livewire::test(CreatePdfDocument::class)
        ->fillForm([
            'title' => 'Offer letter',
            'language' => 'ar',
            'body' => '<p>مرحبا {{name}}</p>',
            'image_layout' => 'separate',
            'header_image' => UploadedFile::fake()->image('header.png', 400, 60),
            'signature_mode' => 'none',
            'stamp_mode' => 'upload',
            'stamp' => UploadedFile::fake()->image('stamp.png', 80, 80),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $document = PdfDocument::firstWhere('title', 'Offer letter');

    expect($document->language)->toBe('ar')
        ->and($document->footer_image)->toBeNull();
    Storage::disk('local')->assertExists([$document->header_image, $document->stamp]);

    $this->get(route('filament.admin.pdf-documents.pdf', $document))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    Livewire::test(EditPdfDocument::class, ['record' => $document->getRouteKey()])
        ->assertActionVisible('saveTop')
        ->assertActionVisible('pdfTop')
        ->assertActionVisible('deleteTop')
        ->fillForm(['title' => 'Offer letter v2'])
        ->callAction('saveTop')
        ->assertNotified()
        ->callAction('finalizeTop');

    $document->refresh();

    expect($document->title)->toBe('Offer letter v2');

    expect($document->reference)->toBe('DOC-'.now()->year.'-0001')
        ->and(PdfDocumentResource::canEdit($document))->toBeFalse()
        ->and(PdfDocumentResource::canDelete($document))->toBeTrue();
    Storage::disk('local')->assertExists($document->pdf_path);

    Livewire::test(ListPdfDocuments::class)
        ->assertTableActionHidden('edit', $document)
        ->assertTableActionVisible('pdf', $document)
        ->assertTableActionVisible('delete', $document);

    Livewire::test(EditPdfDocument::class, ['record' => $document->getRouteKey()])
        ->assertRedirect(PdfDocumentResource::getUrl('index'))
        ->assertNotified('This document is finalized');

    Livewire::test(ListPdfDocuments::class)->callTableAction('delete', $document);

    expect(PdfDocument::find($document->id))->toBeNull();
    Storage::disk('local')->assertMissing($document->pdf_path);
});

test('a new document is created from a template once', function () {
    $this->actingAs(superAdmin(), 'admin');
    $template = DocumentTemplate::create([
        'name' => 'Letter',
        'language' => 'ar',
        'body' => '<p>Template body</p>',
        'header_image' => 'documents/images/header.png',
        'stamp' => 'documents/images/stamp.png',
    ]);

    Livewire::test(ListPdfDocuments::class)
        ->callAction('fromTemplate', data: ['template_id' => $template->id, 'title' => 'For ACME'])
        ->assertHasNoActionErrors();

    $document = PdfDocument::firstWhere('title', 'For ACME');

    expect($document->only('language', 'body', 'header_image', 'stamp', 'footer_image'))->toBe([
        'language' => 'ar',
        'body' => '<p>Template body</p>',
        'header_image' => 'documents/images/header.png',
        'stamp' => 'documents/images/stamp.png',
        'footer_image' => null,
    ]);

    $template->update(['body' => '<p>changed</p>']);

    expect($document->refresh()->body)->toBe('<p>Template body</p>');
});

test('picking a template on the document form fills its fields', function () {
    Storage::fake('local');
    $this->actingAs(superAdmin(), 'admin');
    $header = UploadedFile::fake()->image('header.png', 400, 60)->store('documents/images', 'local');
    $template = DocumentTemplate::create([
        'name' => 'Letter',
        'language' => 'ar',
        'body' => '<p>Template body</p>',
        'image_layout' => 'separate',
        'header_image' => $header,
        'signature_mode' => 'none',
        'stamp_mode' => 'default',
    ]);

    Livewire::test(CreatePdfDocument::class)
        ->fillForm(['template_id' => $template->id])
        ->assertSchemaStateSet(['image_layout' => 'separate', 'language' => 'ar', 'signature_mode' => 'none'])
        ->fillForm(['title' => 'From picker'])
        ->call('create')
        ->assertHasNoFormErrors();

    $document = PdfDocument::firstWhere('title', 'From picker');

    expect($document->only('template_id', 'language', 'header_image', 'signature_mode'))
        ->toBe(['template_id' => $template->id, 'language' => 'ar', 'header_image' => $header, 'signature_mode' => 'none'])
        ->and($document->body)->toContain('Template body');

    // Opening the edit page shows the saved choice and keeps the document's own content.
    $document->update(['body' => '<p>Edited</p>']);

    Livewire::test(EditPdfDocument::class, ['record' => $document->getRouteKey()])
        ->assertSchemaStateSet(['template_id' => $template->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($document->refresh()->body)->toContain('Edited');

    $template->delete();

    expect($document->refresh()->template_id)->toBeNull();
});

test('preview saves the form first and refuses invalid input', function () {
    $this->actingAs(superAdmin(), 'admin');
    $document = PdfDocument::create(['title' => 'Before', 'body' => '<p>x</p>']);

    Livewire::test(EditPdfDocument::class, ['record' => $document->getRouteKey()])
        ->fillForm(['title' => 'After'])
        ->call('saveForPreview')
        ->assertReturned(true)
        ->fillForm(['title' => ''])
        ->call('saveForPreview')
        ->assertHasFormErrors(['title' => 'required']);

    expect($document->refresh()->title)->toBe('After');
});

test('templates use the same fields as new documents', function () {
    $this->actingAs(superAdmin(), 'admin');

    Livewire::test(CreateDocumentTemplate::class)
        ->fillForm(['name' => 'Plain', 'language' => 'en', 'body' => '<p>x</p>'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Template created')
        ->assertRedirect(DocumentTemplateResource::getUrl('index'));

    expect(DocumentTemplate::firstWhere('name', 'Plain')->body)->toContain('x');
});

test('signature and stamp follow their default, upload or none choice', function () {
    Storage::fake('local');
    $general = UploadedFile::fake()->image('general.png', 40, 20)->store('settings', 'local');
    $own = UploadedFile::fake()->image('own.png', 40, 20)->store('documents/images', 'local');
    app(GeneralSettings::class)->fill([
        'signature' => $general,
        'stamp' => $general,
        'logo' => null,
    ])->save();

    $document = PdfDocument::create(['title' => 'T', 'body' => '<p>x</p>', 'stamp_mode' => 'upload', 'stamp' => $own]);
    $pdf = app(DocumentPdf::class);
    $image = fn (string $name) => (fn () => $this->pdfDocumentImage($document, $name))->call($pdf);

    expect($image('signature'))->toBe(Storage::disk('local')->get($general))
        ->and($image('stamp'))->toBe(Storage::disk('local')->get($own))
        ->and($image('header_image'))->toBeNull()
        ->and($document->update(['signature_mode' => 'none', 'stamp_mode' => 'default']))->toBeTrue()
        ->and($image('signature'))->toBeNull()
        ->and($image('stamp'))->toBe(Storage::disk('local')->get($general))
        ->and($pdf->renderPdfDocument($document))->toStartWith('%PDF');
});

test('roles, users and contact are hidden from the sidebar but stay reachable', function () {
    expect(RoleResource::shouldRegisterNavigation())->toBeFalse()
        ->and(UserResource::shouldRegisterNavigation())->toBeFalse()
        ->and(Contact::shouldRegisterNavigation())->toBeFalse()
        ->and(General::shouldRegisterNavigation())->toBeTrue();

    $this->actingAs(superAdmin(), 'admin');

    $this->get('/admin/shield/roles')->assertOk();
    $this->get('/admin/contact')->assertOk();
});

test('sidebar groups keep their order in both languages', function (string $locale, array $groups) {
    $this->actingAs(superAdmin(), 'admin')
        ->withSession(['locale' => $locale])
        ->get('/admin')
        ->assertOk()
        ->assertSeeInOrder($groups);
})->with([
    'english' => ['en', ['Documents', 'Settings', 'System']],
    'arabic' => ['ar', ['المستندات', 'إعدادات', 'System']],
]);

test('admins without a role cannot manage users', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/admin/users')
        ->assertForbidden();
});

test('super admins can create a user', function () {
    $this->actingAs(superAdmin(), 'admin');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'mobile' => '99887766',
            'password' => 'secret-password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::firstWhere('email', 'jane@example.com');

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->mobile)->toBe('99887766');
});

test('user creation validates required and unique fields', function () {
    $this->actingAs(superAdmin(), 'admin');
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => '', 'email' => 'taken@example.com', 'password' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'unique', 'password' => 'required']);
});

test('super admins can edit a user', function () {
    $this->actingAs(superAdmin(), 'admin');
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['name' => 'Renamed User'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Renamed User');
});

test('the users table lists and searches users', function () {
    $this->actingAs(superAdmin(), 'admin');
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->email)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1));
});

test('super admins can create an admin with a role', function () {
    $this->actingAs(superAdmin(), 'admin');
    $role = Role::findOrCreate('panel_user', 'admin');

    Livewire::test(CreateAdmin::class)
        ->fillForm([
            'name' => 'Second Admin',
            'email' => 'second@example.com',
            'mobile' => '55443322',
            'password' => 'secret-password',
            'roles' => [$role->getKey()],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Admin::firstWhere('email', 'second@example.com')->hasRole('panel_user'))->toBeTrue();
});

test('only one letterhead stays default', function () {
    $first = DocumentLayout::create(['name' => 'A', 'is_default' => true]);
    $second = DocumentLayout::create(['name' => 'B', 'is_default' => true]);

    expect($first->refresh()->is_default)->toBeFalse()
        ->and($second->refresh()->is_default)->toBeTrue()
        ->and(DocumentLayout::defaultId())->toBe($second->id);
});

test('a letterhead is saved with header and footer from one form', function () {
    $this->actingAs(superAdmin(), 'admin');

    Livewire::test(CreateDocumentLayout::class)
        ->fillForm([
            'name' => 'ACME',
            'header_content' => '<p>Head</p>',
            'footer_content' => '<p>Foot</p>',
            'logo_position' => 'right',
            'accent_color' => '#112233',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $letterhead = DocumentLayout::firstWhere('name', 'ACME');

    expect($letterhead->header_content)->toContain('Head')
        ->and($letterhead->footer_content)->toContain('Foot')
        ->and($letterhead->logo_position)->toBe('right')
        ->and($letterhead->accent_color)->toBe('#112233');
});

test('migration merges header and footer pairs without changing any document', function () {
    $migration = require database_path('migrations/2026_11_09_000002_merge_headers_and_footers_into_letterheads.php');
    $migration->down();

    $layout = fn (string $type, string $name) => DB::table('document_layouts')->insertGetId([
        'type' => $type, 'name' => $name, 'content' => $name, 'logo_position' => 'left',
    ]);
    $header = $layout('header', 'H');
    $footerOne = $layout('footer', 'F1');
    $footerTwo = $layout('footer', 'F2');
    $layout('footer', 'Unused');

    $document = fn (string $title, ?int $h, ?int $f) => DB::table('documents')->insertGetId([
        'title' => $title, 'header_id' => $h, 'footer_id' => $f,
    ]);
    $first = $document('First', $header, $footerOne);
    $sameFooter = $document('Same', $header, $footerOne);
    $otherFooter = $document('Other', $header, $footerTwo);
    $footerOnly = $document('Only', null, $footerTwo);
    $none = $document('None', null, null);
    $template = DB::table('document_templates')->insertGetId(['name' => 'T', 'header_id' => $header, 'footer_id' => $footerTwo]);

    $migration->up();

    $letterhead = fn (int $id) => Document::find($id)->layout;

    expect($letterhead($first)->only('header_content', 'footer_content'))->toBe(['header_content' => 'H', 'footer_content' => 'F1'])
        ->and($letterhead($sameFooter)->id)->toBe($letterhead($first)->id)
        ->and($letterhead($otherFooter)->only('header_content', 'footer_content'))->toBe(['header_content' => 'H', 'footer_content' => 'F2'])
        ->and($letterhead($footerOnly)->only('header_content', 'footer_content'))->toBe(['header_content' => null, 'footer_content' => 'F2'])
        ->and($letterhead($none))->toBeNull()
        ->and(DB::table('document_templates')->find($template)->layout_id)->toBe($letterhead($otherFooter)->id)
        ->and(DocumentLayout::where('footer_content', 'Unused')->whereNull('header_content')->exists())->toBeTrue()
        ->and(DocumentLayout::orderBy('name')->pluck('name')->all())->toBe(['F2', 'H', 'H + F2', 'Unused']);
});

test('a document from a template copies its content once', function () {
    $this->actingAs(superAdmin(), 'admin');
    $letterhead = DocumentLayout::create(['name' => 'H']);
    $template = DocumentTemplate::create([
        'name' => 'Letter',
        'language' => 'ar',
        'layout_id' => $letterhead->id,
        'body' => '<p>{{client}}</p>',
        'stamp_mode' => 'none',
    ]);

    // The button is hidden, so this tests the action's logic directly.
    Livewire::test(ListDocuments::class)->assertActionHidden('fromTemplate');

    $document = Document::create(['title' => 'For ACME', ...$template->refresh()->only(Document::TEMPLATE_FIELDS)]);

    expect($document->language)->toBe('ar')
        ->and($document->layout_id)->toBe($letterhead->id)
        ->and($document->stamp_mode)->toBe('none')
        ->and($document->placeholder_values)->toBe(['client' => '']);

    $template->update(['body' => '<p>changed</p>']);

    expect($document->refresh()->body)->toBe('<p>{{client}}</p>');
});

test('letterhead images render and its signature and stamp beat general settings', function () {
    Storage::fake('local');
    $store = fn (string $name) => UploadedFile::fake()->image("{$name}.png", 40, 20)->store('documents/images', 'local');

    $letterhead = DocumentLayout::create([
        'name' => 'Images',
        'header_content' => '<p>Head text</p>',
        ...collect(['header_image', 'footer_image', 'background_image', 'signature', 'stamp'])
            ->mapWithKeys(fn (string $name) => [$name => $store($name)]),
    ]);
    $document = Document::create(['title' => 'T', 'layout_id' => $letterhead->id, 'body' => '<p>x</p>']);
    $pdf = app(DocumentPdf::class);

    foreach (['signature', 'stamp'] as $name) {
        expect((fn () => $this->image($document, $name))->call($pdf))
            ->toBe(Storage::disk('local')->get($letterhead->{$name}));
    }

    expect($pdf->render($document))->toStartWith('%PDF');
});

test('document language reads the same on the form and both lists', function () {
    $this->actingAs(superAdmin(), 'admin');
    Document::create(['title' => 'T', 'language' => 'ar', 'body' => '<p>x</p>']);
    DocumentTemplate::create(['name' => 'N', 'language' => 'en', 'body' => '<p>x</p>']);

    $this->get('/admin/documents')->assertSee(['Document language', 'Arabic (right to left)']);
    $this->get('/admin/document-templates')->assertSee(['Document language', 'English (left to right)']);
    $this->get('/admin/documents/create')->assertSee(['Document language', 'English (left to right)', 'Arabic (right to left)']);
});

test('bulk delete removes drafts but never finalized documents', function () {
    $this->actingAs(superAdmin(), 'admin');
    $draft = Document::create(['title' => 'Draft', 'body' => '<p>x</p>']);
    $final = Document::create(['title' => 'Final', 'body' => '<p>x</p>', 'status' => 'final', 'reference' => 'DOC-2026-0001']);

    Livewire::test(ListDocuments::class)
        ->assertTableBulkActionExists('delete')
        ->callTableBulkAction('delete', [$draft, $final]);

    expect(Document::find($draft->id))->toBeNull()
        ->and(Document::find($final->id))->not->toBeNull()
        ->and($final->delete())->toBeFalse()
        ->and(Document::find($final->id))->not->toBeNull();
});

test('templates and layouts can be bulk deleted', function () {
    $this->actingAs(superAdmin(), 'admin');
    $templates = collect(['A', 'B'])->map(fn (string $name) => DocumentTemplate::create(['name' => $name]));
    $layouts = collect(['A', 'B'])->map(fn (string $name) => DocumentLayout::create(['name' => $name]));

    Livewire::test(ListDocumentTemplates::class)->callTableBulkAction('delete', $templates);
    Livewire::test(ListDocumentLayouts::class)->callTableBulkAction('delete', $layouts);

    expect(DocumentTemplate::count())->toBe(0)
        ->and(DocumentLayout::count())->toBe(0);
});

test('bulk delete needs the delete permission', function () {
    test()->artisan('shield:generate', ['--all' => true, '--option' => 'permissions', '--panel' => 'admin'])->assertSuccessful();
    $role = Role::findOrCreate('viewer', 'admin');
    $role->syncPermissions(Permission::where('guard_name', 'admin')->where('name', 'ViewAny:DocumentTemplate')->get());
    $admin = Admin::factory()->create();
    $admin->assignRole($role);
    $this->actingAs($admin, 'admin');
    $template = DocumentTemplate::create(['name' => 'Keep']);

    Livewire::test(ListDocumentTemplates::class)->callTableBulkAction('delete', [$template]);

    expect(DocumentTemplate::find($template->id))->not->toBeNull();
});

test('finalize requires placeholders, then locks the document and keeps its pdf', function () {
    Storage::fake('local');
    $admin = superAdmin();
    $this->actingAs($admin, 'admin');
    $document = Document::create(['title' => 'T', 'body' => '<p>{{client}}</p>']);

    Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
        ->callAction('finalize');

    expect($document->refresh()->isFinal())->toBeFalse();

    $document->update(['placeholder_values' => ['client' => 'ACME']]);

    Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
        ->callAction('finalize')
        ->assertRedirect();

    $document->refresh();
    $stored = Storage::disk('local')->get($document->pdf_path);

    expect($document->isFinal())->toBeTrue()
        ->and($stored)->toStartWith('%PDF')
        ->and(DocumentResource::canEdit($document))->toBeFalse()
        ->and(DocumentResource::canDelete($document))->toBeFalse();

    $this->get("/admin/documents/{$document->id}/edit")->assertForbidden();

    $response = $this->get("/admin/documents/{$document->id}/pdf?download=1")->assertOk();

    expect($response->getContent())->toBe($stored)
        ->and($response->headers->get('Content-Disposition'))->toContain("attachment; filename={$document->reference}.pdf");

    // Editing a master layout later cannot change what was issued.
    $this->get("/admin/documents/{$document->id}/pdf")->assertHeader('Content-Type', 'application/pdf');
});

test('document pdfs need a login and permission', function () {
    $document = Document::create(['title' => 'T', 'body' => '<p>x</p>']);

    $this->get("/admin/documents/{$document->id}/pdf")->assertRedirect('/admin/login');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get("/admin/documents/{$document->id}/pdf")
        ->assertForbidden();
});

test('default images come from settings and uploads override them', function () {
    Storage::fake('local');
    $image = UploadedFile::fake()->image('s.png', 10, 10)->get();
    Storage::disk('local')->put('settings/sig.png', $image);
    Storage::disk('local')->put('settings/sig.txt', 'not an image');
    app(GeneralSettings::class)->fill(['signature' => 'settings/sig.png'])->save();

    $read = fn (Document $document) => (fn () => $this->image($document, 'signature'))->call(app(DocumentPdf::class));

    expect($read(new Document(['signature_mode' => 'default'])))->toBe($image)
        ->and($read(new Document(['signature_mode' => 'none'])))->toBeNull()
        ->and($read(new Document(['signature_mode' => 'upload', 'signature' => 'settings/sig.txt'])))->toBeNull();
});

test('activity log filters can be applied', function () {
    $this->actingAs(superAdmin(), 'admin');

    Livewire::test(ListActivities::class)
        ->filterTable('old_properties', ['old' => 'name'])
        ->assertOk()
        ->filterTable('new_properties', ['new' => 'name'])
        ->assertOk()
        ->resetTableFilters()
        ->assertOk();
});
