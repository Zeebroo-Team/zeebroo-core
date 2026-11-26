<?php

test('the home route redirects guests to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

test('the login page returns a successful response', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
});
