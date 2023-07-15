<h1 align="center">
	<img src="https://github.com/orisai/.github/blob/main/images/repo_title.png?raw=true" alt="Orisai"/>
	<br/>
	Nette Mail
</h1>

<p align="center">
	Extras for <a href="https://github.com/nette/mail">nette/mail</a>
</p>

<p align="center">
	📄 Check out our <a href="docs/README.md">documentation</a>.
</p>

<p align="center">
	💸 If you like Orisai, please <a href="https://orisai.dev/sponsor">make a donation</a>. Thank you!
</p>

<p align="center">
	<a href="https://github.com/orisai/nette-mail/actions?query=workflow%3ACI">
		<img src="https://github.com/orisai/nette-mail/workflows/CI/badge.svg">
	</a>
	<a href="https://coveralls.io/r/orisai/nette-mail">
		<img src="https://badgen.net/coveralls/c/github/orisai/nette-mail/v1.x?cache=300">
	</a>
	<a href="https://dashboard.stryker-mutator.io/reports/github.com/orisai/nette-mail/v1.x">
		<img src="https://badge.stryker-mutator.io/github.com/orisai/nette-mail/v1.x">
	</a>
	<a href="https://packagist.org/packages/orisai/nette-mail">
		<img src="https://badgen.net/packagist/dt/orisai/nette-mail?cache=3600">
	</a>
	<a href="https://packagist.org/packages/orisai/nette-mail">
		<img src="https://badgen.net/packagist/v/orisai/nette-mail?cache=3600">
	</a>
	<a href="https://choosealicense.com/licenses/mpl-2.0/">
		<img src="https://badgen.net/badge/license/MPL-2.0/blue?cache=3600">
	</a>
<p>

##

```neon
extensions:
	orisai.mail: OriNette\Mail\DI\MailExtension

orisai.mail:
	# Add debug panel
	debug:
		panel: %debugMode%
		tempDir: %tempDir%/mailPanel
		cleanup: '-1 hour'
	# Add original mailer to used mailers
	mailers:
		- @mail.mailer

services:
	# Disable autowiring of mailer from original mail extension
	mail.mailer:
		autowired: false
```
