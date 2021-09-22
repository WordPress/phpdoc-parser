import React, { useState } from 'react';
import axios from 'axios';
import { __ } from '@wordpress/i18n';
import { CustomSnackbarProps } from '@aivec/react-material-components/Snackbar';
import { createErrorMessage, createRequestBody } from '@aivec/reqres-utils';
import { InjectedSettings } from './Types';

const { endpoint, importOutput } = avcpdp as InjectedSettings;

const Import = ({
  setSnackbar,
  closeSnackbar,
}: {
  setSnackbar: (props: CustomSnackbarProps) => void;
  closeSnackbar: () => void;
}): JSX.Element => {
  const [loading, setLoading] = useState(false);
  const [fname, setFolderName] = useState('');
  const [trashOldRefs, setTrashOldRefs] = useState(true);
  const [output, setOutput] = useState(importOutput);

  const updateFolderName = (event: React.ChangeEvent<HTMLInputElement>): void => {
    setFolderName(String(event.target.value));
  };

  const updateTrashOldRefs = (event: React.ChangeEvent<HTMLInputElement>): void => {
    setTrashOldRefs(event.target.checked);
  };

  const updateOutput = (out: string): void => {
    setOutput(out);
  };

  const create = async (): Promise<void> => {
    closeSnackbar();
    setLoading(true);
    try {
      const { data }: { data: string } = await axios.post(
        `${endpoint}/v1/parser/create/${fname}`,
        createRequestBody(avcpdp as InjectedSettings, { trashOldRefs }),
      );
      updateOutput(data);
    } catch (error) {
      const message = String(createErrorMessage(avcpdp as InjectedSettings, error));
      setSnackbar({ open: true, type: 'error', message });
      setLoading(false);
    }
  };

  return (
    <div>
      <h3>{__('Import', 'wp-parser')}</h3>
      <div className="avc-v2 flex column-nowrap mb-05rem-c-all">
        <div>{__('Enter the folder name of the plugin/theme/composer-package', 'wp-parser')}</div>
        <div>
          <input type="text" onChange={updateFolderName} value={fname} />
        </div>
        <div>
          <label>
            <input
              className="avc-v2 mr-05rem"
              type="checkbox"
              onChange={updateTrashOldRefs}
              defaultChecked={trashOldRefs}
            />
            {__('Trash old references', 'wp-parser')}
          </label>
        </div>
        <div>
          {!fname ? (
            <button type="button" className="button disabled button-primary">
              {__('Import', 'wp-parser')}
            </button>
          ) : loading ? (
            <button type="button" className="button disabled button-primary">
              {__('Importing...', 'wp-parser')}
            </button>
          ) : (
            <button type="button" className="button button-primary" onClick={create}>
              {__('Import', 'wp-parser')}
            </button>
          )}
        </div>
        {output && (
          <div className="output-container">
            <pre>{output}</pre>
          </div>
        )}
      </div>
    </div>
  );
};

export default Import;
