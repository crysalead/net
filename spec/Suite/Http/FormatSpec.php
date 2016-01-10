<?php
namespace Lead\Net\Spec\Suite\Http;

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

});
