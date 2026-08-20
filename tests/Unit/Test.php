<?php

test('that true is true', function () {
    expect(true)->toBeTrue();
});

test('that false is false', function () {
    expect(false)->toBeFalse();
});
test('that 1 is 1', function () {
    expect(1)->toBe(1);
});