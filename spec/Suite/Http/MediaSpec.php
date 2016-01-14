<?php
namespace Lead\Net\Spec\Suite\Http;

use Lead\Collection\Collection;
use Lead\Net\Http\Format;
use Lead\Net\Http\Media;
use Lead\Net\Http\Response;

describe("Media", function() {

    afterEach(function() {

        Media::reset();

    });

    describe("::set()", function() {

        it("supports custom handlers", function() {

            Media::set('csv', ['application/csv'], [
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

            $response->set($data);

            $expected = 'John,Doe,"123 Main St.","Anytown, CA",91724' . "\n";
            $expected .= 'Jane,Doe,"124 Main St.","Anytown, CA",91724' . "\n";
            expect($response->body())->toBe($expected);

            expect((string) $response->headers['Content-Type'])->toBe('Content-Type: application/csv');

        });

    });

    describe("::remove()", function() {

        it("remove a format", function() {

            Media::set('csv', []);
            Media::set('pdf', []);

            expect(Media::get('csv'))->not->toBe(null);
            expect(Media::get('pdf'))->not->toBe(null);

            Media::remove(['csv', 'pdf']);

            expect(Media::get('csv'))->toBe(null);
            expect(Media::get('pdf'))->toBe(null);

        });

    });

    describe("::encode()", function() {

        it("encodes in json", function() {

            $json = Media::encode('json', ['key' => 'value']);

            expect($json)->toBe('{"key":"value"}');

        });

        it("encodes objects in json", function() {

            $json = Media::encode('json', new Collection(['key' => 'value']));

            expect($json)->toBe('{"key":"value"}');

        });

        it("encodes in form data", function() {

            $json = Media::encode('form', ['key1' => 'value1', 'key2' => 'value2']);

            expect($json)->toBe('key1=value1&key2=value2');

        });

    });

    describe("::decode()", function() {

        it("decodes json", function() {

            $data = Media::decode('json', '{"key":"value"}');

            expect($data)->toBe(['key' => 'value']);

        });

        it("decodes form data", function() {

            $data = Media::decode('form', 'key1=value1&key2=value2');

            expect($data)->toBe(['key1' => 'value1', 'key2' => 'value2']);

        });

    });

    describe("::to()", function() {

        it("delegates to `::encode()`", function() {

            expect(Media::class)->toReceive('::encode')->with('json', '', ['key' => 'value']);

            Media::to('json', '', ['key' => 'value']);

        });

    });

});
