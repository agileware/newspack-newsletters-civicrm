/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment, useEffect } from '@wordpress/element';
import { BaseControl, CheckboxControl, Spinner, Notice, TextControl, PanelBody, PanelRow, FormToggle, ColorPicker } from '@wordpress/components';
import { InspectorControls, MediaPlaceholder, RichText } from "@wordpress/block-editor";
import {PluginDocumentSettingPanel} from "@wordpress/edit-post";
import Sidebar from "../../newsletter-editor/sidebar";
import {PublicSettings} from "../../newsletter-editor/public"; //note that this autocompletes incorrectly

function justShowSomethingOnTheDamnConsole(status) {
	console.log(status);
}

const ProviderSidebar = ( {
	renderSubject,
	renderFrom,
	inFlight,
	newsletterData,
	apiFetch,
	postId,
	updateMeta,
} ) => {
	const campaign = newsletterData.mailing;
	const groups = newsletterData.groups ? newsletterData.groups : [];

	const setIncludeGroup = ( groupId, value ) => {
		const method = value ? 'PUT' : 'DELETE';
		apiFetch( {
			path: `/newspack-newsletters/v1/civicrm/${ postId }/group-include/${ groupId }`,
			method,
		} );
	};

	const setExcludeGroup = ( groupId, value ) => {
		const method = value ? 'PUT' : 'DELETE';
		apiFetch( {
			path: `/newspack-newsletters/v1/civicrm/${ postId }/group-exclude/${ groupId }`,
			method,
		} );
	};

	const setValue = value => {
		const f = value; //something something
	};

	const setSender = ( { senderName, senderEmail } ) =>
		apiFetch( {
			path: `/newspack-newsletters/v1/civicrm/${ postId }/sender`,
			data: {
				from_name: senderName,
				reply_to: senderEmail,
			},
			method: 'POST',
		} );

	useEffect(() => {
		if ( campaign ) {
			updateMeta( {
				senderName: campaign.from_name,
				senderEmail: campaign.from_email,
			} );
		}
	}, [ campaign ]);

	if ( ! campaign ) {
		return (
			<div className="newspack-newsletters__loading-data">
				{ __( 'Retrieving CiviCRM data...', 'newspack-newsletters' ) }
				<Spinner />
			</div>
		);
	}

	const { scheduled_date } = campaign || {};

	if ( scheduled_date ) {
		return (
			<Notice status="success" isDismissible={ false }>
				{ __( 'Mailing has already been sent.', 'newspack-newsletters' ) }
			</Notice>
		);
	}

	return (
		<Fragment>
			{ renderSubject() }
			<PluginDocumentSettingPanel
				name="newsletters-settings-panel-civi-groups-include"
				title={ __( 'Civi Groups: Include', 'newspack-newsletters' ) }
			>
				{ groups.map( ( { id, name } ) => (
					<CheckboxControl
						key={ id }
						label={ name }
						value={ id }
						checked={ newsletterData.include_groups.some( group => group.entity_id == id ) }
						onChange={ value => setIncludeGroup( id, value ) }
						disabled={ inFlight }
					/>
				) ) }
				<Notice status="success" isDismissible={ false }>
					Recipients: { newsletterData.recipient_count }
				</Notice>
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="newsletters-settings-panel-civi-groups-exclude"
				title={ __( 'Civi Groups: Exclude', 'newspack-newsletters' ) }
			>
				{ groups.map( ( { id, name } ) => (
					<CheckboxControl
						key={ id }
						label={ name }
						value={ id }
						checked={ newsletterData.exclude_groups.some( group => group.entity_id == id ) }
						onChange={ value => setExcludeGroup( id, value ) }
						disabled={ inFlight }
					/>
				) ) }
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="newsletters-settings-panel-civi-settings"
				title={ __( 'Civi Settings', 'newspack-newsletters' ) }
			>
				<TextControl
					label={ __( 'Civi example text setting (does nothing)', 'newspack-newsletters' ) }
					className="newspack-newsletters-civicrm-textcontrol"
					onChange={ value => setValue( value ) }
				/>
				<FormToggle id="toggle_show_footer" label="Toggle that does nothing" checked="" onChange="" />
			</PluginDocumentSettingPanel>

			{ renderFrom( { handleSenderUpdate: setSender } ) }

		</Fragment>
	);
};

export default ProviderSidebar;
