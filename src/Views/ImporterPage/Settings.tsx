import React, { useState } from 'react';
import axios from 'axios';
import { __ } from '@wordpress/i18n';
import { CustomSnackbarProps } from '@aivec/react-material-components/Snackbar';
import { createErrorMessage, createRequestBody } from '@aivec/reqres-utils';
import { InjectedSettings } from './Types';

const { endpoint, sourceFoldersAbspath } = avcpdp as InjectedSettings;

const UpdateServerFolderLocation = ({
  setSnackbar,
  closeSnackbar,
}: {
  setSnackbar: (props: CustomSnackbarProps) => void;
  closeSnackbar: () => void;
}): JSX.Element => {
  const [loading, setLoading] = useState(false);
  const [path, setFolderPath] = useState(sourceFoldersAbspath);

  const set = (event: React.ChangeEvent<HTMLInputElement>): void => {
    setFolderPath(String(event.target.value));
  };

  const update = async (): Promise<void> => {
    closeSnackbar();
    setLoading(true);
    try {
      const { data }: { data: string } = await axios.post(
        `${endpoint}/v1/updateSourceFoldersAbspath`,
        createRequestBody(avcpdp as InjectedSettings, { path }),
      );
      if (data !== 'success') {
        throw new Error();
      }
      setSnackbar({
        open: true,
        type: 'success',
        message: __('Updated', 'wp-parser'),
      });
    } catch (error) {
      const message = String(createErrorMessage(avcpdp as InjectedSettings, error));
      setSnackbar({ open: true, type: 'error', message });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <h3>{__('Source folders parent path', 'wp-parser')}</h3>
      <div className="avc-v2 mb-05rem-c-all">
        <div>
          {__('Enter the absolute path to the location of the source folders', 'wp-parser')}
        </div>
        <input type="text" onChange={set} value={path} />
        <div>
          {loading ? (
            <button type="button" className="button disabled button-primary">
              {__('Updating...', 'wp-parser')}
            </button>
          ) : (
            <button type="button" className="button button-primary" onClick={update}>
              {__('Update', 'wp-parser')}
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default UpdateServerFolderLocation;
