import example from './example';
import mailchimp from './mailchimp';
import constant_contact from './constant_contact';
import campaign_monitor from './campaign_monitor';
import civicrm from './civicrm';

const SERVICE_PROVIDERS = {
	mailchimp,
	constant_contact,
	campaign_monitor,
	civicrm,
};

export const getServiceProvider = () => {
	const serviceProvider =
		window && window.newspack_newsletters_data && window.newspack_newsletters_data.service_provider;
	return SERVICE_PROVIDERS[ serviceProvider || 'example' ];
};
