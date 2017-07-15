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
            $mail->returnPath("teal'c@chulâk.com");
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
Date: %a%
Subject: Test addresses
From: Teal'c <teal'c@xn--chulk-6qa.com>
Return-Path: teal'c@xn--chulk-6qa.com
Reply-To: Teal'c <teal'c@xn--chulk-6qa.com>
To: Rya'c <rya'c@xn--chulk-6qa.com>, A Name <name1@example.com>,
 "A\\Name" <name2@example.com>, "A.Name" <name3@example.com>, "A\\'Name'" <name4@example.com>,
 "'A.Name'" <name5@example.com>, "\"A Name\"" <name6@example.com>,
 =?UTF-8?B?0JDQvdC00YDQtdC5INCR0LXQu9GL0Lk=?= <biely@xn--andre-gta.com>
Message-ID: <%a%@%a%>

EOD;

            expect($mail->toMessage())->toMatch(Template::pattern($expected));


        });

        fit("export alternatives", function() {

            $mail = new Message();

            $mail->subject('Test multipart/alternatives');
            $mail->from("From <from@from.com>");
            $mail->addTo("To <to@to.com>");
            $mail->html('<h1>Hello World!</h1>', 'Hello World!');

            $expected = <<<'EOD'
MIME-Version: 1.0
Date: %a%
Subject: Test addresses
From: Teal'c <teal'c@xn--chulk-6qa.com>
Return-Path: teal'c@xn--chulk-6qa.com
Reply-To: Teal'c <teal'c@xn--chulk-6qa.com>
To: Rya'c <rya'c@xn--chulk-6qa.com>, A Name <name1@example.com>,
 "A\\Name" <name2@example.com>, "A.Name" <name3@example.com>, "A\\'Name'" <name4@example.com>,
 "'A.Name'" <name5@example.com>, "\"A Name\"" <name6@example.com>,
 =?UTF-8?B?0JDQvdC00YDQtdC5INCR0LXQu9GL0Lk=?= <biely@xn--andre-gta.com>
Message-ID: <%a%@%a%>

EOD;

            var_dump($mail->toMessage());
            expect($mail->toMessage())->toMatch(Template::pattern($expected));


        });

    });

});