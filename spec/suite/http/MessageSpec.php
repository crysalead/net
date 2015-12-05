<?php
namespace net\spec\suite\http;

use net\http\Message;
use net\http\Headers;

describe("Message", function() {

    describe("->__construct()", function() {

        it("sets default values", function() {

            $message = new Message();
            expect($message->headers())->toBeAnInstanceOf(Headers::class);

        });

        it("parses headers", function() {

            $message = new Message(['headers' => [
                'HTTP/1.x 200 OK',
                'User-Agent: Mozilla/5.0',
                'Cache-Control: no-cache',
                'Cookie: foo1=bar1, foo2=bar2, foo3=bar3',
                'Set-Cookie: foo1=bar1; Path=/',
                'Set-Cookie: foo2=bar2; Path=/',
                'Set-Cookie: foo3=bar3; Path=/'
            ]]);

            $expected =<<<EOD
HTTP/1.x 200 OK\r
User-Agent: Mozilla/5.0\r
Cache-Control: no-cache\r
Cookie: foo1=bar1, foo2=bar2, foo3=bar3\r
Set-Cookie: foo1=bar1; Path=/\r
Set-Cookie: foo2=bar2; Path=/\r
Set-Cookie: foo3=bar3; Path=/\r
\r

EOD;

            expect(Headers::toHeader($message->headers()))->toBe($expected);

        });

    });

    describe("->type()", function() {

        it("returns the content type initialized using headers", function() {

            $message = new Message(['headers' => [
                'Content-Type: application/json; charset=UTF-8'
            ]]);
            expect($message->type())->toBe('application/json');
            expect($message->headers()['Content-Type']->data())->toBe('application/json; charset=UTF-8');

        });

        it("returns the content type initialized using the type option", function() {

            $message = new Message(['type' => 'application/json']);
            expect($message->type())->toBe('application/json');
            expect($message->headers()['Content-Type']->data())->toBe('application/json');

        });

    });

    describe("->type()", function() {

        it("returns the protocol", function() {

            $message = new Message();
            expect($message->protocol())->toBe('HTTP/1.1');

        });
    });

});

//     public function testReturnJsonIfNoBufferAndEmptyBody() {
//         $this->message->type("json");
//         $result = $this->message->body("", array('encode' => true));
//         $this->assertIdentical('[""]', $result);
//     }

//     public function testReturnMergedJsonWithEmptyBody() {
//         $this->message->type("json");
//         $result = $this->message->body("", array('encode' => true));
//         $this->assertIdentical('[""]', $result);

//         $result = $this->message->body("", array('encode' => true));
//         $this->assertIdentical('["",""]', $result);
//     }

//     public function testReturnMergedJson() {
//         $this->message->type("json");
//         $result = $this->message->body(array("myvar1" => "val1"), array('encode' => true));
//         $this->assertIdentical('{"myvar1":"val1"}', $result);

//         $result = $this->message->body(array("myvar2" => "val2"), array('encode' => true));
//         $this->assertIdentical('{"myvar1":"val1","myvar2":"val2"}', $result);
//     }

//     public function testReturnJsonIfNoBufferAndArrayBody() {
//         $this->message->type("json");
//         $result = $this->message->body(array(""), array('encode' => true));
//         $this->assertIdentical('[""]', $result);
//     }

//     public function testReturnProperlyWithEmptyValues() {
//         $this->message->type("json");

//         $result = $this->message->body(array(
//             'active' => '0'
//         ), array('encode' => true));
//         $this->assertIdentical('{"active":"0"}', $result);

//         $this->message = new Message();
//         $this->message->type("json");

//         $result = $this->message->body(array(
//             'myvar' => ''
//         ), array('encode' => true));
//         $this->assertIdentical('{"myvar":""}', $result);
//     }

//     public function testEmptyEncodeInJson() {
//         $this->message->type("json");
//         $result = $this->message->body(null, array('encode' => true));
//         $this->assertIdentical("", $result);
//     }

//     public function testEmptyArrayEncodeInJson() {
//         $this->message->type("json");
//         $result = $this->message->body(array(), array('encode' => true));
//         $this->assertIdentical("[]", $result);
//     }

//     public function testEmptyJsonDecode() {
//         $this->message->type("json");
//         $result = $this->message->body("{}", array('decode' => true));
//         $this->assertIdentical(array(), $result);
//     }

//     public function testEmptyJsonArrayDecode() {
//         $this->message->type("json");
//         $result = $this->message->body("[]", array('decode' => true));
//         $this->assertIdentical(array(), $result);
//     }
// }
