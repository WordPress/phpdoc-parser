import React, { useState } from 'react';
import Snackbar, { CustomSnackbarProps } from '@aivec/react-material-components/Snackbar';
import Settings from './Settings';
import Import from './Import';

const initialSnackbarProps: CustomSnackbarProps = {
  open: false,
  type: 'default',
  backgroundColor: undefined,
  icon: undefined,
  textColor: undefined,
  closeIconColor: undefined,
  message: '',
};

const ImporterPage = (): JSX.Element => {
  const [snackbar, setOurSnackbar] = useState<CustomSnackbarProps>(initialSnackbarProps);

  const setSnackbar = (props: CustomSnackbarProps): void => {
    setOurSnackbar({ ...snackbar, ...props });
  };

  const closeSnackbar = (): void => {
    setOurSnackbar({ ...snackbar, open: false });
  };

  return (
    <>
      <Settings setSnackbar={setSnackbar} closeSnackbar={closeSnackbar} />
      <hr />
      <Import setSnackbar={setSnackbar} closeSnackbar={closeSnackbar} />
      <Snackbar
        open={snackbar.open}
        type={snackbar.type}
        message={snackbar.message}
        onClose={closeSnackbar}
      />
    </>
  );
};

export default ImporterPage;
