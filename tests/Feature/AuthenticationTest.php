<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_register_creates_user_with_hashed_password_and_logs_in(): void
    {
        $this->post(route('register'), [
            'name' => 'Nguyễn Văn A',
            'email' => 'vana@vinoi.com',
            'password' => 'Matkhau123@',
            'password_confirmation' => 'Matkhau123@',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'vana@vinoi.com')->first();

        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertNotSame('Matkhau123@', $user->password);
        $this->assertTrue(Hash::check('Matkhau123@', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->post(route('register'), [
            'name' => 'Nguyễn Văn B',
            'email' => 'vanb@vinoi.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'vanb@vinoi.com']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'email' => 'demo@vinoi.com',
            'password' => 'Demo123@',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'sai-mat-khau',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_validates_email_format(): void
    {
        $this->post(route('login'), [
            'email' => 'khong-phai-email',
            'password' => 'whatever',
        ])->assertSessionHasErrors('email');
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guest_can_open_landing_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Quản lý chi tiêu cho người Việt.');
    }

    public function test_authenticated_user_is_redirected_from_landing_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_guest_cannot_open_protected_pages(): void
    {
        foreach (['dashboard', 'transactions.index', 'transactions.create', 'reports.index', 'profile.show'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }
}
