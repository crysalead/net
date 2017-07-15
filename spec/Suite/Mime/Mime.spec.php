<?php
namespace Lead\Net\Spec\Suite\Mime;

use Exception;
use Lead\Net\Mime\Mime;

describe("Mime", function() {

    describe("::optimalEncoding()", function() {

        it("detects 7bit data", function() {

            expect(Mime::optimalEncoding('a'))->toBe('7bit');

        });

        it("detects quoted-printable data", function() {

            expect(Mime::optimalEncoding('poésie'))->toBe('quoted-printable');

        });

        it("detects base64 data", function() {

            expect(Mime::optimalEncoding('Я'))->toBe('base64');

        });

    });

    describe("::encode()", function() {

        it("encodes 7bit data", function() {

            expect(Mime::encode('a', '7bit'))->toBe('a');

        });

        it("encodes 8bit encoded data", function() {

            expect(Mime::encode('rЯ', '8bit'))->toBe('rЯ');

        });

        it("encodes binary data", function() {

            expect(Mime::encode('rЯ', 'binary'))->toBe('rЯ');

        });

        it("encodes quoted-printable encoded data", function() {

            expect(Mime::encode('Я', 'quoted-printable'))->toBe('=D0=AF');

        });

        it("encodes base64 encoded data", function() {

            expect(Mime::encode('bar', 'base64'))->toBe('YmFy');

        });

    });

    describe("::encodeEmail()", function() {

        it("encodes IDN emails", function() {

            $emails = [
                'name@مثال.إختبار' => 'name@xn--mgbh0fb.xn--kgbechtv',
                'name@مثال.آزمایشی' => 'name@xn--mgbh0fb.xn--hgbk6aj7f53bba',
                'name@例子.测试' => 'name@xn--fsqu00a.xn--0zwm56d',
                'name@例子.測試' => 'name@xn--fsqu00a.xn--g6w251d',
                'name@пример.испытание' => 'name@xn--e1afmkfd.xn--80akhbyknj4f',
                'name@उदाहरण.परीक्षा' => 'name@xn--p1b6ci4b4b3a.xn--11b5bs3a9aj6g',
                'name@παράδειγμα.δοκιμή' => 'name@xn--hxajbheg2az3al.xn--jxalpdlp',
                'name@실례.테스트' => 'name@xn--9n2bp8q.xn--9t4b11yi5a',
                'name@בײַשפּיל.טעסט' => 'name@xn--fdbk5d8ap9b8a8d.xn--deba0ad',
                'name@例え.テスト'  => 'name@xn--r8jz45g.xn--zckzah',
                'name@உதாரணம்.பரிட்சை' => 'name@xn--zkc6cc5bi7f6e.xn--hlcj6aya9esc7a',
                'name@derhausüberwacher.de' => 'name@xn--derhausberwacher-pzb.de',
                'name@renangonçalves.com' => 'name@xn--renangonalves-pgb.com',
                'name@рф.ru' => 'name@xn--p1ai.ru',
                'name@δοκιμή.gr' => 'name@xn--jxalpdlp.gr',
                'name@ফাহাদ্১৯.বাংলা' => 'name@xn--65bj6btb5gwimc.xn--54b7fta0cc',
                'name@𐌀𐌖𐌋𐌄𐌑𐌉·𐌌𐌄𐌕𐌄𐌋𐌉𐌑.gr' => 'name@xn--uba5533kmaba1adkfh6ch2cg.gr',
                'name@guangdong.广东' => 'name@guangdong.xn--xhq521b',
                'name@gwóźdź.pl' => 'name@xn--gwd-hna98db.pl',
                'name@άέήίΰαβγδεζηθικλμνξοπρσστυφχ.com' => 'name@xn--hxacdefghijklmnopqrstuvw0caz0a1a2a.com'
            ];

            foreach ($emails as $email => $encodedEmail) {
                expect(Mime::encodeEmail($email))->toBe($encodedEmail);
            }

        });

    });

    describe("::encodeValue()", function() {

        it("encodes values", function() {

            expect(Mime::encodeValue('abc'))->toBe('abc');
            expect(Mime::encodeValue('abc'))->toBe('abc');
            expect(Mime::encodeValue('a\bc'))->toBe('"a\\\\bc"');
            expect(Mime::encodeValue('"a\bc"'))->toBe('"\"a\\\\bc\""');
            expect(Mime::encodeValue('a.bc'))->toBe('"a.bc"');

            expect(Mime::encodeValue('Андрей Белый'))->toBe('=?UTF-8?B?0JDQvdC00YDQtdC5INCR0LXQu9GL0Lk=?=');
            expect(Mime::encodeValue('poésie'))->toBe('=?UTF-8?Q?po=C3=A9sie?=');

        });

    });

    describe("::decodeValue()", function() {

        it("decodes values", function() {

            expect(Mime::decodeValue('=?UTF-8?B?0JDQvdC00YDQtdC5INCR0LXQu9GL0Lk=?='))->toBe('Андрей Белый');
            expect(Mime::decodeValue('=?UTF-8?Q?po=C3=A9sie?='))->toBe('poésie');

        });

    });

});