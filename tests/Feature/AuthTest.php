<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_랜딩과_소개_페이지는_누구나_볼_수_있다(): void
    {
        foreach (['home', 'solution', 'data', 'pricing', 'login', 'register'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_회원가입하면_바로_분석_화면으로_보낸다(): void
    {
        $response = $this->post(route('register'), [
            'name' => '홍길동',
            'email' => 'gildong@example.com',
            'company' => '오다네트웍스',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('analyses.create'));
        $this->assertAuthenticated();

        $user = User::firstWhere('email', 'gildong@example.com');
        $this->assertSame('오다네트웍스', $user->company);
        $this->assertNull($user->marketing_agreed_at);
    }

    public function test_약관에_동의하지_않으면_가입할_수_없다(): void
    {
        $this->post(route('register'), [
            'name' => '홍길동',
            'email' => 'gildong@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
    }

    public function test_마케팅_수신에_동의하면_시각이_기록된다(): void
    {
        $this->post(route('register'), [
            'name' => '홍길동',
            'email' => 'gildong@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'terms' => '1',
            'marketing' => '1',
        ]);

        $this->assertNotNull(User::firstWhere('email', 'gildong@example.com')->marketing_agreed_at);
    }

    public function test_로그인하면_마지막_접속시각이_갱신된다(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password1234')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password1234'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_비밀번호가_틀리면_로그인에_실패한다(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password1234')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_관리자만_데이터_현황을_볼_수_있다(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('admin.data'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.data'))
            ->assertOk();
    }
}
