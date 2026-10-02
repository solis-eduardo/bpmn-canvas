<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageTitleTest extends TestCase
{
    use RefreshDatabase;

    private function windowTitle(string $html): string
    {
        preg_match('#<title>(.*?)</title>#s', $html, $matches);

        return html_entity_decode($matches[1] ?? '');
    }

    public function test_window_titles_use_a_pipe_separator_and_no_dashes_in_every_locale(): void
    {
        Storage::fake('diagrams');
        $user = User::factory()->create(['is_admin' => true]);
        $diagram = Diagram::factory()->for($user, 'author')->for($user, 'owner')->create(['title' => 'Order Flow']);

        foreach (['en', 'es', 'pt_BR'] as $locale) {
            app()->setLocale($locale);
            session(['locale' => $locale]);

            $titles = [
                $this->windowTitle($this->get('/login')->getContent()),
                $this->windowTitle($this->actingAs($user)->get('/diagrams')->getContent()),
                $this->windowTitle($this->actingAs($user)->get("/diagrams/{$diagram->id}")->getContent()),
                $this->windowTitle($this->actingAs($user)->get("/admin/users/{$user->id}/edit")->getContent()),
            ];

            foreach ($titles as $title) {
                $this->assertNotSame('', $title);
                $this->assertDoesNotMatchRegularExpression('/[—–]/u', $title, "[$locale] $title");
            }
        }
    }

    public function test_edit_user_screen_title_has_no_dash(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get("/admin/users/{$admin->id}/edit")
            ->assertOk()
            ->assertSee(__('messages.edit_user').': '.$admin->name);
    }
}
