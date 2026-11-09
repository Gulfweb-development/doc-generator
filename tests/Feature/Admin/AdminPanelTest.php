<?php

use App\Filament\Pages\Contact;
use App\Filament\Pages\General;
use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\DocumentLayouts\Pages\ListDocumentLayouts;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\DocumentTemplates\Pages\ListDocumentTemplates;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Admin;
use App\Models\Document;
use App\Models\DocumentLayout;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\DocumentPdf;
use App\Settings\GeneralSettings;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

test('roles and contact are hidden from the sidebar but stay reachable', function () {
    expect(RoleResource::shouldRegisterNavigation())->toBeFalse()
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

test('only one layout of each type stays default', function () {
    $first = DocumentLayout::create(['type' => 'header', 'name' => 'A', 'is_default' => true]);
    $footer = DocumentLayout::create(['type' => 'footer', 'name' => 'F', 'is_default' => true]);
    $second = DocumentLayout::create(['type' => 'header', 'name' => 'B', 'is_default' => true]);

    expect($first->refresh()->is_default)->toBeFalse()
        ->and($second->refresh()->is_default)->toBeTrue()
        ->and($footer->refresh()->is_default)->toBeTrue()
        ->and(DocumentLayout::defaultId('header'))->toBe($second->id);
});

test('a document from a template copies its content once', function () {
    $this->actingAs(superAdmin(), 'admin');
    $header = DocumentLayout::create(['type' => 'header', 'name' => 'H']);
    $template = DocumentTemplate::create([
        'name' => 'Letter',
        'language' => 'ar',
        'header_id' => $header->id,
        'body' => '<p>{{client}}</p>',
        'stamp_mode' => 'none',
    ]);

    Livewire::test(ListDocuments::class)
        ->callAction('fromTemplate', data: ['template_id' => $template->id, 'title' => 'For ACME'])
        ->assertHasNoActionErrors();

    $document = Document::firstWhere('title', 'For ACME');

    expect($document->language)->toBe('ar')
        ->and($document->header_id)->toBe($header->id)
        ->and($document->stamp_mode)->toBe('none')
        ->and($document->placeholder_values)->toBe(['client' => '']);

    $template->update(['body' => '<p>changed</p>']);

    expect($document->refresh()->body)->toBe('<p>{{client}}</p>');
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
    $layouts = collect(['A', 'B'])->map(fn (string $name) => DocumentLayout::create(['type' => 'header', 'name' => $name]));

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
