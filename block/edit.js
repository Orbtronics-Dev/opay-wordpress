/**
 * Opay Payment Button — Gutenberg block editor component.
 *
 * Rendered inside the block editor; shows a dropdown of existing payment
 * buttons and a label text control. Output is a static shortcode.
 */

import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
  PanelBody,
  SelectControl,
  TextControl,
  Placeholder,
} from "@wordpress/components";

const { buttons = [], environment = "test" } = window.opayBlockData || {};

const buttonOptions = [
  {
    value: "",
    label: __("— Select a payment button —", "orbtronics-payment-gateway"),
  },
  ...buttons,
];

export default function Edit({ attributes, setAttributes }) {
  const { buttonId, label } = attributes;
  const blockProps = useBlockProps();

  const selectedButton = buttons.find((b) => b.value === buttonId);

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__("Button Settings", "orbtronics-payment-gateway")}
          initialOpen={true}
        >
          <SelectControl
            label={__("Payment Button", "orbtronics-payment-gateway")}
            value={buttonId}
            options={buttonOptions}
            onChange={(value) => setAttributes({ buttonId: value })}
            help={__(
              "Choose an existing Opay payment button.",
              "orbtronics-payment-gateway",
            )}
          />
          <TextControl
            label={__("Button Label", "orbtronics-payment-gateway")}
            value={label}
            onChange={(value) => setAttributes({ label: value })}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        {!buttonId ? (
          <Placeholder
            icon="money-alt"
            label={__("Opay Payment Button", "orbtronics-payment-gateway")}
            instructions={__(
              "Select a payment button from the Inspector panel on the right.",
              "orbtronics-payment-gateway",
            )}
          />
        ) : (
          <div className="opay-block-preview">
            <button
              type="button"
              className="opay-pay-btn opay-pay-btn--preview"
              disabled
            >
              {label || __("Pay Now", "orbtronics-payment-gateway")}
              {selectedButton && (
                <span className="opay-amount">
                  &nbsp;({selectedButton.label.split("—")[1]?.trim()})
                </span>
              )}
            </button>
            <p className="opay-block-meta">
              {__("Mode:", "orbtronics-payment-gateway")}{" "}
              <strong>{environment}</strong>
              &nbsp;|&nbsp;
              {__("ID:", "orbtronics-payment-gateway")} <code>{buttonId}</code>
            </p>
          </div>
        )}
      </div>
    </>
  );
}
