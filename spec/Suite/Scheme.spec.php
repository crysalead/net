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
                'dict'   => 2628,
                'ftp'    => 21,
                'http'   => 80,
                'https'  => 443,
                'imap'   => 143,
                'sftp'   => 115,
                'smtp'   => 25,
                'smtps'  => 465,
                'ssh'    => 22,
                'telnet' => 23
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

        it("returns protocols default port number", function() {

            expect(Scheme::port('https'))->toBe(443);

        });

    });

    describe("::reset()", function() {

        it("resets to default schemes", function() {

            $defaults = [
                'ftp'    => 21,
                'ssh'    => 22,
                'telnet' => 23,
                'smtp'   => 25,
                'http'   => 80,
                'sftp'   => 115,
                'imap'   => 143,
                'https'  => 443,
                'smtps'  => 587
            ];

            Scheme::unregister('http');
            expect(Scheme::registered())->not->toBe($defaults);

            Scheme::reset();
            expect(Scheme::registered())->toBe($defaults);

        });

    });

});