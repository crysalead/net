<?php
namespace Lead\Net\Spec\Suite\Http;

use Lead\Collection\Collection;
use Lead\Net\Http\Format;
use Lead\Net\Http\Response;

describe("Format", function() {

    beforeEach(function() {

        Format::reset();

    });

    describe("::set()", function() {

        it("supports custom handlers", function() {

            Format::set('csv', [
                'type'   => ['application/csv'],
                'encode' => function($data) {
                    ob_start();
                    $out = fopen('php://output', 'w');
                    foreach ($data as $record) {
                        fputcsv($out, $record);
                    }
                    fclose($out);
                    return ob_get_clean();
                }
            ]);

            $response = new Response();
            $response->format('csv');

            $data = [
                ['John', 'Doe', '123 Main St.', 'Anytown, CA', '91724'],
                ['Jane', 'Doe', '124 Main St.', 'Anytown, CA', '91724']
            ];

            $response->body($data);

            $expected = 'John,Doe,"123 Main St.","Anytown, CA",91724' . "\n";
            $expected .= 'Jane,Doe,"124 Main St.","Anytown, CA",91724' . "\n";
            expect($response->plain())->toBe($expected);

            expect((string) $response->headers['Content-Type'])->toBe('Content-Type: application/csv');

        });

    });

    describe("::remove()", function() {

        it("remove a format", function() {

            Format::set([
                'csv' => ['application/csv'],
                'pdf' => ['application/pdf']
            ]);

            expect(Format::get('csv'))->not->toBe(null);
            expect(Format::get('pdf'))->not->toBe(null);

            Format::remove(['csv', 'pdf']);

            expect(Format::get('csv'))->toBe(null);
            expect(Format::get('pdf'))->toBe(null);

        });

    });

    describe("::encode()", function() {

        it("encodes in json", function() {

            $json = Format::encode('json', ['key' => 'value']);

            expect($json)->toBe('{"key":"value"}');

        });

        it("encodes objects in json", function() {

            $json = Format::encode('json', new Collection(['key' => 'value']));

            expect($json)->toBe('{"key":"value"}');

        });

        it("encodes in form data", function() {

            $json = Format::encode('form', ['key1' => 'value1', 'key2' => 'value2']);

            expect($json)->toBe('key1=value1&key2=value2');

        });

    });

    describe("::decode()", function() {

        it("decodes json", function() {

            $data = Format::decode('json', '{"key":"value"}');

            expect($data)->toBe(['key' => 'value']);

        });

        it("decodes form data", function() {

            $data = Format::decode('form', 'key1=value1&key2=value2');

            expect($data)->toBe(['key1' => 'value1', 'key2' => 'value2']);

        });

    });

    describe("::to()", function() {

        it("delegates to `::encode()`", function() {

            expect(Format::class)->toReceive('::encode')->with('json', '', ['key' => 'value']);

            Format::to('json', '', ['key' => 'value']);

        });

    });

});
