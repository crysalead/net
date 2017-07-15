<?php
namespace Lead\Net\Spec\Suite\Mime;

use InvalidArgumentException;
use Lead\Net\Mime\Address;

describe("Address", function() {

    describe("->__construct()", function() {

        it("supports full address as email", function() {

            $address = new Address('Žluťoučký kůň <kun1@oučký.com>');
            expect($address->email())->toBe('kun1@oučký.com');
            expect($address->name())->toBe('Žluťoučký kůň');

        });

        it("does not require name for instantiation", function() {

            $address = new Address('kun1@oučký.com');
            expect($address->email())->toBe('kun1@oučký.com');
            expect($address->name())->toBe(null);

        });

        it("supports name as second parameter", function() {

            $address = new Address('kun1@oučký.com', 'Žluťoučký kůň');
            expect($address->email())->toBe('kun1@oučký.com');
            expect($address->name())->toBe('Žluťoučký kůň');

        });

        it("throws when the address is empty", function() {

            $closure = function() {
                $address = new Address('');
            };

            expect($closure)->toThrow(new InvalidArgumentException("Email can't be empty."));

        });

        it("throws when the address is invalid", function() {

            foreach ([
                'azAZ09-_' => null,
                'ázÁZ09-_' => null,
            ] as $email => $name) {
                $closure = function() use ($email, $name) {
                    $address = new Address($email, $name);
                };

                expect($closure)->toThrow(new InvalidArgumentException("Invalid email `'{$email}'`, can't be parsed."));
            }

        });

    });

    describe("->toString()", function() {

        it("creates an encoded string representation of the email address", function() {

            $address = new Address('kun1@oučký.com', 'Žluťoučký kůň');
            expect($address->toString())->toBe('=?UTF-8?B?xb1sdcWlb3XEjWvDvSBrxa/FiA==?= <kun1@xn--ouk-noa2f.com>');

        });

    });

});