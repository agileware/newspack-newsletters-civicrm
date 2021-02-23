# newspack-newsletters
Author email newsletters in WordPress

## CiviCRM support

**Proof of concept** - do not allow anywhere near production

* Can set subject, from-name, from-email
* Can send test emails
* Lists Civi groups for include/exclude
* Shows number of recipients

### Notes

* Currently does zero authentication against Civi permissions
* Schedules mailing for immediately
* Groups are a list of checkboxes, which obviously doesn't scale. I can't immediately see a multi-select component in Gutenberg.
* Sends template through MJML API for processing, but [this issue](https://github.com/Automattic/newspack-newsletters/issues/400) suggests there might be a package that can do it now
* Haven't worked out how to remove the irrelevant settings yet ('Preview text', 'Disable ads' etc.)
* In theory it should be possible to add tokens to the Block Editor toolbars. Not sure if they'd survive parsing through MJML

## Relevant files

* PHP: includes/service-providers/civicrm
* JS: src/service-providers/civicrm
* The only changes outside of these directories (so far) are to include said files

## Use

1. In Settings, add MJML App ID and Secret Key
2. Set Service Provider to CiviCRM
3. Click Newsletters in the left menu. Create a new one.

## Development

Run `composer update && npm install`.
(Windows: make sure git is installed)

Run `npm run build`.
