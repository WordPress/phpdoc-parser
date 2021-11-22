import { InjectedErrorObjects, InjectedRouterVariables } from '@aivec/reqres-utils';

export interface InjectedVars extends InjectedErrorObjects, InjectedRouterVariables {
  reactDomNode: string;
}
