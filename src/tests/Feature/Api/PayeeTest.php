<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Payee;
use App\Models\User;
use Tests\TestCase;

class PayeeTest extends TestCase
{
    public function test_user_can_list_their_payees()
    {
        $user = $this->authenticatedUser();
        Payee::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/payees');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'payees')
            ->assertJsonStructure([
                'payees' => [
                    '*' => ['id', 'name', 'auto_assign_category']
                ]
            ]);
    }

    public function test_user_can_search_payees()
    {
        $user = $this->authenticatedUser();
        Payee::factory()->create(['user_id' => $user->id, 'name' => 'Grocery Store']);
        Payee::factory()->create(['user_id' => $user->id, 'name' => 'Gas Station']);
        Payee::factory()->create(['user_id' => $user->id, 'name' => 'Restaurant']);

        $response = $this->getJson('/api/payees?search=grocery');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'payees')
            ->assertJsonFragment(['name' => 'Grocery Store']);
    }

    public function test_user_can_create_payee()
    {
        $user = $this->authenticatedUser();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $payeeData = [
            'name' => 'New Payee',
            'auto_assign_category_id' => $category->id
        ];

        $response = $this->postJson('/api/payees', $payeeData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Payee']);

        $this->assertDatabaseHas('payees', [
            'user_id' => $user->id,
            'name' => 'New Payee',
            'auto_assign_category_id' => $category->id
        ]);
    }

    public function test_user_can_update_payee()
    {
        $user = $this->authenticatedUser();
        $payee = Payee::factory()->create(['user_id' => $user->id]);

        $response = $this->putJson("/api/payees/{$payee->id}", [
            'name' => 'Updated Payee Name'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Payee Name']);
    }

    public function test_user_can_delete_payee()
    {
        $user = $this->authenticatedUser();
        $payee = Payee::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/payees/{$payee->id}");

        $response->assertStatus(200);
        $this->assertModelMissing($payee);
    }
}
