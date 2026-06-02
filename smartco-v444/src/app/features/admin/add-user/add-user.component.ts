import { Component, DestroyRef, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators, AbstractControl, ValidationErrors, AsyncValidatorFn } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatSelectModule } from '@angular/material/select';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { Router } from '@angular/router';
import { firstValueFrom, map, of, switchMap, timer } from 'rxjs';
import { AdminUsersService } from './admin-users.service';

// Async validator to check if email is unique
function emailUniqueValidator(svc: AdminUsersService): AsyncValidatorFn {
  return (c: AbstractControl) => {
    const v = String(c.value || '').trim();
    if (!v) return of(null);
    return timer(300).pipe(
      // debounce simple
      switchMap(() => svc.checkEmailUnique(v)),
      map(r => (r?.ok && r.data?.unique) ? null : { emailTaken: true })
    );
  };
}
// Note: in a real app, you would want to handle errors from the checkEmailUnique call and possibly return a different validation error (e.g. { emailCheckFailed: true })
@Component({
  selector: 'app-add-user',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    MatFormFieldModule, MatInputModule, MatButtonModule,
    MatSelectModule, MatCardModule, MatIconModule,
  ],
  templateUrl: './add-user.component.html',
  styleUrls: ['./add-user.component.scss']
})
// This component is for demonstration purposes and may not cover all edge cases or best practices for a production app.
export class AddUserComponent {
  private fb = inject(FormBuilder);
  private svc = inject(AdminUsersService);
  private router = inject(Router);

  saving = signal(false);
  errorMsg = signal<string | null>(null);
  successId = signal<number | null>(null);

  roles = ['admin','manager','employee'] as const;
  statuses = ['active','inactive','banned'] as const;

  form = this.fb.group({
    name: ['', [Validators.required, Validators.minLength(3)]],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(6)]],
    role: ['employee', [Validators.required]],
    status: ['active', [Validators.required]],
  });

  // add async validator for email uniqueness
  async save() {
    this.errorMsg.set(null);
    this.successId.set(null);
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }

    this.saving.set(true);
    try {
      const id = await firstValueFrom(this.svc.createUser(this.form.getRawValue() as any));
      this.successId.set(id);
      // this.router.navigateByUrl('/admin/users');
      this.form.patchValue({ name: '', email: '', password: '', role: 'employee', status: 'active' });
      this.form.markAsPristine();
    } catch (e: any) {
      this.errorMsg.set(e?.message || 'Failed to create user');
    } finally {
      this.saving.set(false);
    }
  }
}