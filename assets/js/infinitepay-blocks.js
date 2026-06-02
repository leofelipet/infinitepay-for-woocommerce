( () => {
	'use strict';

	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { getPaymentMethodData } = window.wc.wcSettings;
	const { decodeEntities } = window.wp.htmlEntities;
	const { sanitizeHTML } = window.wc.sanitize;
	const { RawHTML } = window.wp.element;
	const { __ } = window.wp.i18n;
	const { createElement: el } = window.wp.element;

	const settings = getPaymentMethodData( 'infinitepay', {} );
	const defaultLabel = __( 'InfinitePay', 'infinitepay' );
	const label =
		decodeEntities( settings?.title || '' ) || defaultLabel;

	const Description = () =>
		el( RawHTML, {
			children: sanitizeHTML( settings.description || '' ),
		} );

	const Label = ( props ) => {
		const { PaymentMethodLabel } = props.components;
		return el( PaymentMethodLabel, { text: label } );
	};

	registerPaymentMethod( {
		name: 'infinitepay',
		label: el( Label, null ),
		content: el( Description, null ),
		edit: el( Description, null ),
		canMakePayment: () => true,
		ariaLabel: label,
		supports: {
			features: settings?.supports ?? [ 'products' ],
		},
	} );
} )();
