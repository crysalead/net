<?php
namespace Lead\Net\Spec\Suite\Mime;

use Exception;
use Lead\Net\Mime\Message;
use Lead\Net\Spec\Helper\Template;

describe("Message", function() {

    describe("->toMessage()", function() {

        it("encodes email adresses", function() {

            $mail = new Message();

            $mail->subject('Test addresses');
            $mail->from("Teal'c <teal'c@chulâk.com>");
            $mail->returnPath("bounce@chulâk.com");
            $mail->addReplyTo("Teal'c <teal'c@chulâk.com>");
            $mail->addTo("Rya'c <rya'c@chulâk.com>");
            $mail->addTo('A Name <name1@example.com>');
            $mail->addTo('A\Name <name2@example.com>');
            $mail->addTo('A.Name <name3@example.com>');
            $mail->addTo("A\'Name' <name4@example.com>");
            $mail->addTo("'A.Name' <name5@example.com>");
            $mail->addTo('"A Name" <name6@example.com>');
            $mail->addTo('Андрей Белый <biely@andreï.com>');

            $expected = <<<'EOD'
MIME-Version: 1.0
Subject: Test addresses
From: Teal'c <teal'c@xn--chulk-6qa.com>
Return-Path: <bounce@xn--chulk-6qa.com>
Reply-To: Teal'c <teal'c@xn--chulk-6qa.com>
To: Rya'c <rya'c@xn--chulk-6qa.com>, A Name <name1@example.com>, "A\\Name"
 <name2@example.com>, "A.Name" <name3@example.com>, "A\\'Name'"
 <name4@example.com>, "'A.Name'" <name5@example.com>, "\"A Name\""
 <name6@example.com>, =?UTF-8?B?0JDQvdC00YDQtdC5INCR0LXQu9GL0Lk=?=
 <biely@xn--andre-gta.com>
Date: %a%
Message-ID: <%S%@%S%>
EOD;

            expect($mail->toMessage())->toMatch(Template::pattern($expected));


        });

        it("export alternatives", function() {

            $mail = new Message();

            $mail->subject('Test multipart/alternatives');
            $mail->from("From <from@from.com>");
            $mail->addTo("To <to@to.com>");
            $mail->html('<h1>Hello World!</h1>', 'Hello World!');

            $expected = <<<'EOD'
MIME-Version: 1.0
Subject: Test multipart/alternatives
From: From <from@from.com>
To: To <to@to.com>
Date: %a%
Message-ID: <%S%@%S%>
Content-Type: multipart/alternative;
 boundary=%S%

--%S%
Content-Type: text/plain; charset=US-ASCII
Content-Transfer-Encoding: 7bit

Hello World!
--%S%
Content-Type: text/html; charset=US-ASCII
Content-Transfer-Encoding: 7bit

<h1>Hello World!</h1>
--%S%--


EOD;

            expect($mail->toMessage())->toMatch(Template::pattern($expected));


        });

    });

});