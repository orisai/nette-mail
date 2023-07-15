# Nette Mail

Extras for [nette/mail](https://github.com/nette/mail)

## Content

- [Setup](#setup)
- [Mailers](#mailers)
	- [File mailer](#file-mailer)
	- [Overwrite recipient mailer](#overwrite-recipient-mailer)
	- [Set message id mailer](#set-message-id-mailer)
	- [To array mailer](#to-array-mailer)
	- [Tracy panel mailer](#tracy-panel-mailer)
- [Mailer test command](#mailer-test-command)

## Setup

Install with [Composer](https://getcomposer.org)

```sh
composer require orisai/nette-mail
```

Register extension

```neon
extensions:
	orisai.mail: OriNette\Mail\DI\MailExtension
```

If you use original `mail` extension, add some compatibility code. Otherwise, add at least one [mailer](#mailers).

```neon
orisai.mail:
	# Add original mailer to used mailers
	mailers:
		- @mail.mailer

services:
	# Disable autowiring of mailer from original mail extension
	mail.mailer:
		autowired: false
```

## Mailers

Add one or more mailers

- all of them are called for every sent email
- if any of them throws an exception, it does not stop other mailers - summary exception is thrown in the end
- you can either reference services or register new mailers just like in `services` section of the configuration

```neon
orisai.mail:
	mailers:
		# Register service, same as in services section
		- OriNette\Mail\Mailer\FileMailer(%tempDir%/mails)
		# Reference existing service
		- @mail.mailer
```

### File mailer

Store emails in `.eml` files for development. You may open `.eml` file in any mail client.

```neon
orisai.mail:
	mailers:
		file:
			factory: OriNette\Mail\Mailer\FileMailer(%tempDir%/mails)
			setup:
				# Delete emails 30 minutes after they were sent
				# Runs when an email is sent
				- setAutoCleanup(\DateTimeImmutable('-30 minutes'))
```

You can also get the stored `.eml` files

```php
use OriNette\Mail\Mailer\FileMailer;

$mailer = $container->getService('orisai.mail.mailer.file');
assert($mailer instanceof FileMailer);

$files = $mailer->getFiles();
```

### Overwrite recipient mailer

Overwrite recipient by configuration - for development in environment with production data.

- Recipients from headers `To`, `Cc` and `Bcc` are removed
- All original recipients are backed up to headers prefixed with `X-Original-` (e.g. `X-Original-To`) for inspection
- Configured recipients are set

```neon
orisai.mail:
	mailers:
		- OriNette\Mail\Mailer\OverwriteRecipientMailer(
			mailer: @mail.mailer
			to: 'FrodoBaggins@TheShire.te'
		)
```

You can also specify multiple primary recipients (to), copies (cc) and blind copies (bcc)

```neon
orisai.mail:
	mailers:
		- OriNette\Mail\Mailer\OverwriteRecipientMailer(
			mailer: @mail.mailer
			to: [
				'harry-potter@hogwarts.co.uk' => 'Harry Potter',
				'dobbythesockmaster@elfmail.com' => 'Dobby, the free elf',
			]
			cc: [
				'MasterYoda@WiseJediCouncil.org' => 'Yoda',
				'QuiGonJinn@LivingForceAcademy.org' => null,
			]
			bcc: [
				'sam@totally.spies' => 'Sam',
				'bubbles@power.puff' => null,
			]
		)
```

### Set message id mailer

Each email you send includes a `Message-ID` header, identifying the unique message. If you don't specify any, nette/mail
generates for you an ID based on hostname. That's fine for http requests, but relies on server configuration in console
and often differs from http hostname.

`SetMessageIdMailer` wraps other mailer to set `Message-ID` http header. On top of the default behavior, it prefers to
use hostname from nette/http request. Benefit is, that you can configure request for console, to make its IDs consistent
with http. To configure console request, you may use [orisai/nette-console](https://github.com/orisai/nette-console).

Nothing to do here, all mailers registered via extension are automatically wrapped by message id mailer.

### To array mailer

Store emails in array for automated tests purposes

```neon
orisai.mail:
	mailers:
		array: OriNette\Mail\Mailer\ToArrayMailer
```

And in test

```php
use OriNette\Mail\Mailer\ToArrayMailer;

$mailer = $container->getService('orisai.mail.mailer.array');
assert($mailer instanceof ToArrayMailer);

$messages = $mailer->getMessages();
```

### Tracy panel mailer

Send emails to Tracy bar panel for development

```neon
orisai.mail:
	debug:
		panel: %debugMode%
```

You can also make mailer persistent across requests and enable extra features like viewing attachments

```neon
orisai.mail:
	debug:
		tempDir: %tempDir%/mailPanel
		# Delete emails 30 minutes after they were sent
		# Runs when an email is sent
		cleanup: '-30 minutes'
```

## Mailer test command

Send test email to verify mailer works

Command is dependent on symfony/console. To use it, install
e.g. [orisai/nette-console](https://github.com/orisai/nette-console).

> Examples assume you run console via executable php script `bin/console`

```sh
# Minimal
bin/console mailer:test to@example.com

# With custom sender, message subject and body
bin/console mailer:test to@example.com --from from@example.com --subject "Message subject" --body "Message <b>html</b> body"
```

If sender is not specified, mail is sent from:

- `from@<current-host>`, if nette/http is configured
- `from@example.org` otherwise
