<?php
namespace Lead\Net\Spec\Suite\Http;

use Lead\Net\NetException;
use Lead\Net\Http\Message;
use Lead\Net\Http\Headers;

describe("Message", function() {

    describe("->__construct()", function() {

        it("sets default values", function() {

            $message = new Message();
            expect($message->headers())->toBeAnInstanceOf(Headers::class);

        });

        it("parses passed headers", function() {

            $message = new Message(['headers' => [
                'HTTP/1.1 200 OK',
                'User-Agent: Mozilla/5.0',
                'Cache-Control: no-cache'
            ]]);

            $expected =<<<EOD
HTTP/1.1 200 OK\r
User-Agent: Mozilla/5.0\r
Cache-Control: no-cache\r
\r

EOD;

            expect(Headers::toHeader($message->headers()))->toBe($expected);

        });

    });

    describe("->protocol()", function() {

        it("returns the protocol", function() {

            $message = new Message();
            expect($message->protocol())->toBe('HTTP/1.1');

        });
    });

    describe("->version()", function() {

        it("returns the protocol version", function() {

            $message = new Message();
            expect($message->version())->toBe('1.1');

        });

        it("gets/sets the protocol version", function() {

            $message = new Message();
            $message->version('1.0');
            expect($message->version())->toBe('1.0');

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

        it("applies UTF-8 as default charset", function() {

            $message = new Message(['type' => 'application/json']);
            expect($message->type())->toBe('application/json');
            expect($message->headers()['Content-Type']->data())->toBe('application/json; charset=UTF-8');

        });

    });

    describe("->encoding()", function() {

        it("gets/sets the content type charset encoding", function() {

            $message = new Message(['headers' => [
                'Content-Type: application/json; charset=UTF-8'
            ]]);
            expect($message->encoding())->toBe('UTF-8');
            expect($message->encoding('utf-16'))->toBe($message);
            expect($message->encoding())->toBe('UTF-16');

        });

        it("throws an exception when no Content-Type has been defined", function() {

            $closure = function() {
                $message = new Message();
                $message->encoding('UTF-8');
            };

            expect($closure)->toThrow(new NetException("Can't set a charset with no valid Content-Type defined."));

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
