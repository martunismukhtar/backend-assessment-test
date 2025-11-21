<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards
        DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards
        DebitCard::factory()->create(); // kartu milik user lain

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        $payload = [
            'type' => 'visa',
            'expiration_date' => '2030-12-31',
        ];

        $response = $this->postJson('/api/debit-cards', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('debit_cards', [
            'type' => 'visa',
            'user_id' => $this->user->id,
            'disabled_at' => null
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/debit-cards/{$card->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $card->id,
        ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create(); // kartu milik user lain

        $response = $this->getJson("/api/debit-cards/{$card->id}");

        $response->assertStatus(403);
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => null, // status disabled
        ]);

        $response = $this->putJson("/api/debit-cards/{$card->id}", [
            'is_active' => true
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('debit_cards', [
            'id' => $card->id,
            'disabled_at' => null,
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => null, // status disabled
        ]);

        $response = $this->putJson("/api/debit-cards/{$card->id}", [
            'is_active' => false
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('debit_cards', [
            'id' => $card->id,
            'disabled_at' => now(),
        ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // wrong: number too short
        $response = $this->putJson("/api/debit-cards/{$card->id}", [
            'number' => '123',
        ]);

        $response->assertStatus(422);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/debit-cards/{$card->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('debit_cards', [
            'id' => $card->id,
        ]);
    }


    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);
        // 2. Buat transaksi terkait debit card tersebut
        DebitCardTransaction::factory()->create([
            'debit_card_id' => $card->id,
            'amount' => 100000,
            'currency_code' => 'IDR',
        ]);
        $response = $this->deleteJson("/api/debit-cards/{$card->id}");

        $response->assertStatus(403); // atau 422 / 409 bergantung implementasi

    }

    // Extra bonus for extra tests :)
}
