import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { map, Observable } from 'rxjs';

// This service is for demonstration purposes and may not cover all edge cases or best practices for a production app.
type ApiResp<T> = { ok: boolean; data: T; error?: { code?: string; message?: string } };

export type NewUserPayload = {
  name: string;
  email: string;
  password: string;
  role: 'admin'|'manager'|'employee';
  status?: 'active'|'inactive'|'banned';
  role_id?: number | null; // optional mapping to roles table
};

// In a real app, you would likely have more methods here for listing users, updating, deleting, etc.
@Injectable({ providedIn: 'root' })
export class AdminUsersService {
  private http = inject(HttpClient);
  private base = '/api/users';

  createUser(payload: NewUserPayload): Observable<number> {
    return this.http.post<ApiResp<{ id: number }>>(`${this.base}/create.php`, payload).pipe(
      map((j) => {
        if (!j?.ok) throw new Error(j?.error?.message || 'Create failed');
        return j.data.id;
      })
    );
  }

  // To check that the email is not duplicated
  checkEmailUnique(email: string) {
    return this.http.get<ApiResp<{ unique: boolean }>>(`${this.base}/check_email.php`, { params: { email } });
  }
}