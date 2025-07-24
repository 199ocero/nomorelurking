<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get('/mentions');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the mentions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/mentions');
    $response->assertStatus(200);
});
