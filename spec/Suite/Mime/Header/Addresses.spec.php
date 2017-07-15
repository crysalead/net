<?php
namespace Lead\Net\Spec\Suite\Mime\Header;

use InvalidArgumentException;
use Lead\Net\Mime\Address;
use Lead\Net\Mime\Header\Addresses;

describe("Addresses", function() {

    beforeEach(function() {

        $this->list = new Addresses();

    });

    describe("->offsetSet()", function() {

        it("adds an address", function() {

            $this->list[] = 'Žluťoučký kůň <kun1@oučký.com>';

            expect(count($this->list))->toBe(1);

            $address = $this->list['kun1@oučký.com'];
            expect($address->email())->toBe('kun1@oučký.com');
            expect($address->name())->toBe('Žluťoučký kůň');

        });

        it("adds an address instance", function() {

            $this->list[] = new Address('Žluťoučký kůň <kun1@oučký.com>');

            expect(count($this->list))->toBe(1);

            $address = $this->list['kun1@oučký.com'];
            expect($address->email())->toBe('kun1@oučký.com');
            expect($address->name())->toBe('Žluťoučký kůň');

        });

    });

});