<?php
namespace Lead\Net\Spec\Suite\Mime;

use Exception;
use Lead\Net\Mime\Message;
use Lead\Net\Spec\Helper\Template;

describe("Message", function() {

    beforeEach(function() {
        $this->path = 'spec/Fixture';
    });

    describe("->from()/->returnPath()/->addTo()/->addReplyTo()", function() {

        it("correctly encodes email adresses", function() {

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

    });

    describe("->html()", function() {

        it("sets html & plain text emails", function() {

            $mail = new Message();

            $mail->subject('Test multipart/alternative');
            $mail->from("From <from@from.com>");
            $mail->addTo("To <to@to.com>");
            $mail->html('<h1>Hello World!</h1>');
            $mail->altBody('Hello World!');

            $expected = <<<'EOD'
MIME-Version: 1.0
Subject: Test multipart/alternative
From: From <from@from.com>
To: To <to@to.com>
Content-Type: multipart/alternative;
 boundary=%S%
Date: %a%
Message-ID: <%S%@%S%>


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

        it("auto builds plain text from html", function() {

            $mail = new Message();

            $mail->subject('Test multipart/alternative');
            $mail->from("From <from@from.com>");
            $mail->addTo("To <to@to.com>");
            $mail->html('<h1>Hello Андрей!</h1>');

            $expected = <<<'EOD'
MIME-Version: 1.0
Subject: Test multipart/alternative
From: From <from@from.com>
To: To <to@to.com>
Content-Type: multipart/alternative;
 boundary=%S%
Date: %a%
Message-ID: <%S%@%S%>


--%S%
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: base64

SGVsbG8g0JDQvdC00YDQtdC5IQ==
--%S%
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: base64

PGgxPkhlbGxvINCQ0L3QtNGA0LXQuSE8L2gxPg==
--%S%--
EOD;

            expect($mail->toMessage())->toMatch(Template::pattern($expected));

        });

        it("auto builds plain text from multiline html and links", function() {

            $mail = new Message();

            $mail->subject('Hello Jane!');
            $mail->from("John Doe <doe@example.com>");
            $mail->addTo("Lady Jane <jane@example.com>");
            $mail->html('<b><span>Příliš </span> <a href="http://green.example.com">žluťoučký</a><br>
    <a
        href=\'http://horse.example.com\'
        style="abc"
    >
        žlutý
        kůň
    </a>
</b>');

            $expected = <<<'EOD'
MIME-Version: 1.0
Subject: Hello Jane!
From: John Doe <doe@example.com>
To: Lady Jane <jane@example.com>
Content-Type: multipart/alternative;
 boundary=%S%
Date: %a%
Message-ID: <%S%@%S%>


--%S%
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit

Příliš žluťoučký <http://green.example.com>
 žlutý kůň <http://horse.example.com>
--%S%
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: 8bit

<b><span>Příliš </span> <a
href="http://green.example.com">žluťoučký</a><br>
    <a
        href='http://horse.example.com'
        style="abc"
    >
        žlutý
        kůň
    </a>
</b>
--%S%--
EOD;

            expect($mail->toMessage())->toMatch(Template::pattern($expected));

        });

        it("supports embedded image and attachment", function() {

            $mail = new Message();

            $mail->subject('Hello Jane!');
            $mail->from("John Doe <doe@example.com>");
            $mail->addTo("Lady Jane <jane@example.com>");
            $mail->html('<b>Sample text</b> <img src="pattern.png">', $this->path);
            $mail->addAttachment($this->path . '/helloworld.gz', iconv('UTF-8', 'WINDOWS-1250', 'žluť/ou čký.zip'));

            $expected = <<<'EOD'
MIME-Version: 1.0
Subject: Hello Jane!
From: John Doe <doe@example.com>
To: Lady Jane <jane@example.com>
Content-Type: multipart/mixed;
 boundary=%S%
Date: %a%
Message-ID: <%S%@%S%>


--%S%
Content-Type: multipart/alternative;
 boundary=%S%


--%S%
Content-Type: text/plain; charset=US-ASCII
Content-Transfer-Encoding: 7bit

Sample text
--%S%
Content-Type: multipart/related;
 boundary=%S%


--%S%
Content-Type: text/html; charset=US-ASCII
Content-Transfer-Encoding: 7bit

<b>Sample text</b> <img
src="cid:%S%@localhost.localdomain">
--%S%
Content-ID: <%S%@localhost.localdomain>
Content-Disposition: attachment; filename="pattern.png"
Content-Type: image/png
Content-Transfer-Encoding: base64

iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFklEQVQImWNgYGBg2LFjx38IwcDA
AAA3+wZPKJuvKQAAAABJRU5ErkJggg==
--%S%--

--%S%--

--%S%
Content-ID: <%S%@localhost.localdomain>
Content-Disposition: attachment; filename="luouk.zip"
Content-Type: application/x-gzip
Content-Transfer-Encoding: base64

H4sICEqsblkAA2hlbGxvd29ybGQudHh0APNIzcnJVwjPL8pJUeQCAN3dFH0NAAAA
--%S%--
EOD;

            expect($mail->toMessage())->toMatch(Template::pattern($expected));

        });

    });


    describe("->addAttachment()", function() {

        it("adds an attachment", function() {

            $mail = new Message();
            $mail->body('Hello World!');
            $mail->addAttachment($this->path . '/helloworld.gz', iconv('UTF-8', 'WINDOWS-1250', 'žluťoučký.zip'));

            $expected = <<<'EOD'
MIME-Version: 1.0
Content-Type: multipart/mixed;
 boundary=%S%
Date: %a%
Message-ID: <%S%@%S%>


--%S%
Content-Type: text/plain; charset=US-ASCII
Content-Transfer-Encoding: 7bit

Hello World!
--%S%
Content-ID: <%S%@%S%>
Content-Disposition: attachment; filename="luouk.zip"
Content-Type: application/x-gzip
Content-Transfer-Encoding: base64

H4sICEqsblkAA2hlbGxvd29ybGQudHh0APNIzcnJVwjPL8pJUeQCAN3dFH0NAAAA
--%S%
EOD;

            expect($mail->toMessage())->toMatch(Template::pattern($expected));

        });

    });

});
