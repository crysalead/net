<?php
namespace Lead\Net\Transport\Spec\Suite\Protocol;

use Lead\Net\Mime\Message;
use Lead\Net\Transport\Protocol\Smtp;

describe("Curl", function() {

    beforeEach(function() {

        beforeEach(function() {
            $this->path = '../net/spec/Fixture';
        });

        $this->mailer = new Smtp([
            'client'   => 'maildev.domain.com',
            'host'     => 'maildev.domain.com',
            'username' => 'maildev',
            'password' => 'maildev',
            'secure'   => 'starttls',
            'port'     => 1025
        ]);

    });

    describe("->send()", function() {

        xit("sends an email", function() {

            $mail = new Message(['client' => 'staffplanning.dev']);

            $mail->subject('Hello man!');
            $mail->from("John Doe <doe@example.com>");
            $mail->addTo("Lady Jane <jane@example.com>");
            $mail->html('<b>Sample pattern</b> <img src="pattern.png">', $this->path);
            $mail->addAttachment($this->path . '/helloworld.gz', iconv('UTF-8', 'WINDOWS-1250', 'žluť/ou čký.zip'));

            $this->mailer->send($mail);

        });

    });

});