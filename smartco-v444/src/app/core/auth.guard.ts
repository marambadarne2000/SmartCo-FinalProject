import { inject } from '@angular/core';
import {
  CanActivateFn,
  Router,
  ActivatedRouteSnapshot,
  RouterStateSnapshot,
  UrlTree
} from '@angular/router';
import { ApiService } from './api.service';
import { catchError, map, of, switchMap } from 'rxjs';

type RequiredPerm = { module: string; action: string };

export const authGuard: CanActivateFn = (
  route: ActivatedRouteSnapshot,
  state: RouterStateSnapshot
) => {
  const api = inject(ApiService);
  const router = inject(Router);

  /** redirecting to the login page*/
  const toLogin = (): UrlTree =>
    router.createUrlTree(['/auth/login'], {
      queryParams: { redirect: state.url }
    });

  /** redirecting to the unauthorized page */
  const toUnauthorized = (): UrlTree =>
    router.createUrlTree(['/unauthorized'], {
      queryParams: { redirect: state.url }
    });

  // requiredRoles: ['admin', 'manager'] or undefined
  const requiredRoles = route.data['roles'] as string[] | undefined; 
  // requiredperm: { module:'projects', action:'update' } or undefined
  const requiredPerm = route.data['permission'] as RequiredPerm | undefined; 

  return api.me().pipe(
    switchMap(meResp => {
      // connected but error in fetching user data (e.g. invalid/expired token)
      if (!meResp.ok) {
        return of(toLogin());
      }

      const user = meResp.data?.user ?? null;
      if (!user) {
        // not logged in or no user data → redirect to login
        return of(toLogin());
      }

      // get role slug from user data (try different possible structures)
      const roleSlug =
        (user as any)?.role?.slug ??
        (user as any)?.role ??
        '';

      /** checking required roles */
      if (requiredRoles && requiredRoles.length > 0) {
        const okRole = requiredRoles
          .map(r => r.toLowerCase())
          .includes(String(roleSlug).toLowerCase());
        if (!okRole) {
          return of(toUnauthorized());
        }
      }

      /** checking required permissions */
      if (requiredPerm) {
        // if permissions are already loaded in the api service, use them to check
        if (api.permissions && api.permissions.length > 0) {
          const ok = api.hasPermission(requiredPerm.module, requiredPerm.action);
          return of(ok ? true : toUnauthorized());
        }

        // upload permissions from the server (if not loaded yet) and check
        return api.getPermissions().pipe(
          map(pResp => {
            if (!pResp.ok) return toUnauthorized();
            api.permissions = pResp.data; // save permissions in the service for future checks
            const ok = api.hasPermission(requiredPerm.module, requiredPerm.action);
            return ok ? true : toUnauthorized();
          })
        );
      }

      // if we reach here, it means the user is authenticated and has the required role/permission (if any) → allow access
      return of(true);
    }),
    catchError(() => of(toLogin()))
  );
};
