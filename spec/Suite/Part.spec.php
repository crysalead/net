<?php
namespace Lead\Storage\Stream\Spec\Suite;

use RuntimeException;
use InvalidArgumentException;
use Lead\Dir\Dir;
use Lead\Net\Part;

describe("Part", function() {

    describe("->__construct()", function() {

        it("sets the encoding", function() {

            $stream = new Part([
                'encoding' => 'base64'
            ]);

            expect($stream->encoding())->toBe('base64');
            $stream->close();

        });
    });

    describe("->read()", function() {

        it("throws an exception if the encoding is enabled", function() {

            $closure = function() {
                $stream = new Part(['encoding' => 'base64']);
                $stream->read();
            };

            expect($closure)->toThrow(new RuntimeException('Stream with encoding cannot be read byte per byte.'));
        });

    });

    describe("->flush()", function() {

        it("flushes 7bit encoded data", function() {

            $stream = new Part(['data' => 'a', 'encoding' => '7bit']);
            expect($stream->flush())->toBe('a');
            $stream->close();

        });

        it("flushes 8bit encoded data", function() {

            $stream = new Part(['data' => 'rЯ', 'encoding' => '8bit']);
            expect($stream->flush())->toBe('rЯ');
            $stream->close();

        });

        it("flushes binary data", function() {

            $stream = new Part(['data' => 'rЯ', 'encoding' => 'binary']);
            expect($stream->flush())->toBe('rЯ');
            $stream->close();

        });

        it("flushes quoted-printable encoded data", function() {

            $stream = new Part(['data' => 'Я', 'encoding' => 'quoted-printable']);
            expect($stream->flush())->toBe('=D0=AF');
            $stream->close();

        });

        it("flushes base64 encoded data", function() {

            $stream = new Part(['data' => 'bar', 'encoding' => 'base64']);
            expect($stream->flush())->toBe('YmFy');
            $stream->close();

        });

    });

    describe("->encoding()", function() {

        it("get/sets the encoding", function() {
            $stream = new Part(['data' => 'foo bar']);
            expect($stream->encoding())->toBe(null);

            expect($stream->encoding('base64'))->toBe($stream);
            expect($stream->encoding())->toBe('base64');
            $stream->close();

        });

    });
});
