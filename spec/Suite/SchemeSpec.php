<?php
namespace Lead\Net\Spec\Suite;

use Exception;
use Lead\Net\NetException;
use Lead\Net\Scheme;

describe("Scheme", function() {

    afterEach(function() {
        Scheme::reset();
    });

    describe("::registered()", function() {

        it("returns default registered schemes", function() {

            expect(Scheme::registered())->toBe([
                'ftp'    => 21,
                'ssh'    => 22,
                'telnet' => 23,
                'smtp'   => 25,
                'http'   => 80,
                'sftp'   => 115,
                'imap'   => 143,
                'https'  => 443,
                'smtps'  => 587
            ]);

        });

    });

    describe("::register()", function() {

        it("registers a scheme", function() {

            Scheme::register('time', 37);
            expect(Scheme::port('time'))->toBe(37);

        });

        it("override a scheme", function() {

            Scheme::register('http', 8080);
            expect(Scheme::port('http'))->toBe(8080);

        });

    });

    describe("::unregister()", function() {

        it("unregisters a scheme", function() {

            Scheme::register('time', 37);
            expect(Scheme::port('time'))->toBe(37);

            Scheme::unregister('time');

            $registered =  Scheme::registered();
            expect(isset($registered['time']))->toBe(false);

        });

    });

    describe("::port()", function() {

        it("throws an exception for unregistered scheme", function() {

            $closure = function() {
                Scheme::port('time');
            };

            expect($closure)->toThrow(new NetException('Unregistered scheme `time`.'));

        });

    });

    describe("::reset()", function() {

        it("resets to default schemes", function() {

            Scheme::unregister('http');
            expect(Scheme::registered())->not->toBe(Scheme::DEFAULTS);

            Scheme::reset();
            expect(Scheme::registered())->toBe(Scheme::DEFAULTS);

        });

    });

});