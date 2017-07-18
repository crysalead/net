<?php
namespace Lead\Net;

use RuntimeException;
use InvalidArgumentException;
use Lead\Storage\Stream\Stream;
use Lead\Net\MixedPart;
use Lead\Net\Part;

describe("MixedPart", function() {

    describe("->__construct()", function() {

        it("asserts the stream is not writable", function() {

            $mimeStream = new MixedPart();
            expect($mimeStream->isWritable())->toBe(false);
            expect($mimeStream->isSeekable())->toBe(true);
            expect($mimeStream->isReadable())->toBe(true);
            expect($mimeStream->boundary())->toBe(null);

            $mimeStream->close();

        });

        it("supports custom boundary", function() {

            $mimeStream = new MixedPart(['mime' => 'multipart/form-data', 'boundary' => 'foo']);
            expect($mimeStream->boundary())->toBe('foo');
            $mimeStream->close();

        });

    });

    describe("->meta()", function() {

        it("returns an empty array", function() {

            $mimeStream = new MixedPart();
            expect($mimeStream->meta())->toBe([]);

            $mimeStream->close();

        });

    });

    describe("->add()", function() {

        it("overwrites mime", function() {

            $mimeStream = new MixedPart(['mime' => 'multipart/form-data', 'boundary' => 'boundary']);
            $mimeStream->add(new Part(['data' => 'bar']), [
                'name'        => 'foo',
                'disposition' => 'inline',
                'mime'        => 'image/png'
            ]);

            $expected = <<<EOD
Content-Type: multipart/form-data; boundary=boundary\r
\r
\r
--boundary\r
Content-Disposition: inline; name="foo"\r
Content-Type: image/png\r
Content-Transfer-Encoding: base64\r
\r
YmFy\r
--boundary--\r

EOD;
            expect($mimeStream->mime())->toBe('multipart/form-data');
            expect($mimeStream->toMessage())->toBe($expected);

            $mimeStream->close();

        });

        it("add custom headers", function() {

            $mimeStream = new MixedPart(['mime' => 'multipart/form-data', 'boundary' => 'boundary']);
            $mimeStream->add(new Part(['data' => 'bar']), [
                'name'        => 'foo',
                'disposition' => 'form-data',
                'headers'     => [
                    'x-foo: "bar"'
                ]
            ]);

            $expected = <<<EOD
Content-Type: multipart/form-data; boundary=boundary\r
\r
\r
--boundary\r
x-foo: "bar"\r
Content-Disposition: form-data; name="foo"\r
Content-Type: text/plain; charset=US-ASCII\r
Content-Transfer-Encoding: quoted-printable\r
\r
bar\r
--boundary--\r

EOD;
            expect($mimeStream->mime())->toBe('multipart/form-data');
            expect($mimeStream->toMessage())->toBe($expected);

            $mimeStream->close();

        });

        it("overwrites disposition", function() {

            $mimeStream = new MixedPart(['mime' => 'multipart/form-data', 'boundary' => 'boundary']);
            $mimeStream->add(new Part(['data' => 'bar']), ['name' => 'foo', 'disposition' => 'attachment']);

            $expected = <<<EOD
Content-Type: multipart/form-data; boundary=boundary\r
\r
\r
--boundary\r
Content-Disposition: attachment; name="foo"\r
Content-Type: text/plain; charset=US-ASCII\r
Content-Transfer-Encoding: quoted-printable\r
\r
bar\r
--boundary--\r

EOD;

            expect($mimeStream->mime())->toBe('multipart/form-data');
            expect($mimeStream->toMessage())->toBe($expected);

            $mimeStream->close();

        });

        it("throws an exception if the `'name'` option is empty using `'Content-Disposition'`", function() {

            $mimeStream = new MixedPart();

            $closure = function() use ($mimeStream) {
                $stream = new Part();
                $mimeStream->add($stream, ['mime' => 'multipart/form-data', 'disposition' => 'attachment']);
            };

            expect($closure)->toThrow(new InvalidArgumentException("The `'name'` option is required."));
            $mimeStream->close();

        });

    });

    describe("->toMessage()", function() {

        it("serializes messages", function() {

            $mimeStream = new MixedPart(['boundary' => 'boundary']);

            $mimeStream->add(new Part(['data' => 'bar']));
            $mimeStream->add(new Part(['data' => 'bam']));

            expect($mimeStream->mime())->toBe('text/plain');
            expect($mimeStream->toMessage())->toBe("Content-Type: text/plain\r\n\r\nbarbam");

            $mimeStream->close();

        });

        it("serializes multipart messages", function() {
            $mimeStream = new MixedPart(['mime' => 'multipart/form-data', 'boundary' => 'boundary']);

            $mimeStream->add(new Part(['data' => 'bar']), [
                'name' => 'foo',
                'disposition' => 'form-data'
            ]);
            $mimeStream->add(new Part(['data' => 'bam']), [
                'name' => 'baz',
                'disposition' => 'form-data'
            ]);

            $expected = <<<EOD
Content-Type: multipart/form-data; boundary=boundary\r
\r
\r
--boundary\r
Content-Disposition: form-data; name="foo"\r
Content-Type: text/plain; charset=US-ASCII\r
Content-Transfer-Encoding: quoted-printable\r
\r\nbar\r
--boundary\r
Content-Disposition: form-data; name="baz"\r
Content-Type: text/plain; charset=US-ASCII\r
Content-Transfer-Encoding: quoted-printable\r
\r
bam\r
--boundary--\r

EOD;

            expect($mimeStream->mime())->toBe('multipart/form-data');
            expect($mimeStream->toMessage())->toBe($expected);

            $mimeStream->close();

        });

        it("serializes multipart messages with non string fields", function() {

            $mimeStream = new MixedPart(['mime' => 'multipart/form-data', 'boundary' => 'boundary']);

            $mimeStream->add(new Part(['data' => 1]), ['name' => 'int','disposition' => 'form-data']);
            $mimeStream->add(new Part(['data' => false]), ['name' => 'bool1','disposition' => 'form-data']);
            $mimeStream->add(new Part(['data' => true]), ['name' => 'bool2', 'disposition' => 'form-data']);
            $mimeStream->add(new Part(['data' => 1.1]), ['name' => 'float','disposition' => 'form-data']);

            $expected = <<<EOD
Content-Type: multipart/form-data; boundary=boundary\r
\r
\r
--boundary\r
Content-Disposition: form-data; name="int"\r
Content-Type: application/octet-stream\r
Content-Transfer-Encoding: base64\r
\r
MQ==\r
--boundary\r
Content-Disposition: form-data; name="bool1"\r
\r
\r
--boundary\r
Content-Disposition: form-data; name="bool2"\r
Content-Type: application/octet-stream\r
Content-Transfer-Encoding: base64\r
\r
MQ==\r
--boundary\r
Content-Disposition: form-data; name="float"\r
Content-Type: text/plain; charset=US-ASCII\r
Content-Transfer-Encoding: quoted-printable\r
\r
1.1\r
--boundary--\r

EOD;

            expect($mimeStream->mime())->toBe('multipart/form-data');
            expect($mimeStream->toMessage())->toBe($expected);

            $mimeStream->close();

        });

    });

    describe("->toString()", function() {

        it("returns multipart content for a single stream using multipart mime", function() {

            $mimeStream = new MixedPart(['mime' => 'multipart/form-data', 'boundary' => 'boundary']);
            $mimeStream->add('hello');

$expected = <<<EOD
\r
--boundary\r
Content-Type: text/plain; charset=US-ASCII\r
Content-Transfer-Encoding: quoted-printable\r
\r
hello\r
--boundary--\r

EOD;

            expect($mimeStream->toString())->toBe($expected);
            $mimeStream->close();

        });

        it("returns simple content for a single stream", function() {

            $mimeStream = new MixedPart(['mime' => 'text/plain']);
            $mimeStream->add('hello');
            expect($mimeStream->toString())->toBe('hello');
            $mimeStream->close();

        });

    });

    describe("->toMessage()", function() {

        it("returns an empty string when empty", function() {

            $mimeStream = new MixedPart(['mime' => 'multipart/form-data']);
            $boundary = $mimeStream->boundary();
            expect($mimeStream->toMessage())->toBe("Content-Type: multipart/form-data; boundary={$boundary}\r\n\r\n\r\n--{$boundary}--\r\n");

            $mimeStream->close();

        });

    });

});
