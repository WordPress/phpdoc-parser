import React from 'react';
import { render } from 'react-dom';
import { InjectedVars } from 'src/Types/Injected';
import App from './ImporterPage';

const { reactDomNode } = avcpdp as InjectedVars;

document.addEventListener('DOMContentLoaded', () =>
  render(<App />, document.querySelector(`#${reactDomNode}`)),
);
