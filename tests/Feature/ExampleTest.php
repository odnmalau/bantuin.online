<?php

test('redirects home to the dashboard', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});
