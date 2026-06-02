// src/app/pages/auth/login/login.component.ts
import { Component, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { ApiService } from '../../../core/api.service';
import { firstValueFrom } from 'rxjs';               // ✅ جديد

@Component({
  selector: 'app-auth-login',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    MatCardModule, MatIconModule, MatFormFieldModule, MatInputModule,
    MatButtonModule, MatProgressSpinnerModule, MatSnackBarModule
  ],
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss']
})
export class AuthLoginComponent implements OnInit {
  private fb = inject(FormBuilder);
  private api = inject(ApiService);
  private router = inject(Router); // Inject the router to navigate between pages
  private route = inject(ActivatedRoute);
  private snack = inject(MatSnackBar);

  hide = true;
  loading = signal(false);
  errorMsg = signal<string | null>(null);

  form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(6)]],
  });

  canSubmit = computed(() => !this.loading() && this.form.valid);

  ngOnInit(): void {
    // Solve the autofill problem by forcing form validation after page load
    setTimeout(() => this.form.updateValueAndValidity({ onlySelf: false, emitEvent: true }), 0);
  }

   forgotPassword(): void {
    this.router.navigate(['/reset-password']);  // ניווט לדף איפוס סיסמה
  }

  // Submit function - handles the login process
  async submit(): Promise<void> {
    this.errorMsg.set(null);
    if (this.loading()) return; // Prevent multiple clicks
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.errorMsg.set('Please fill in the required fields correctly.');
      return;
    }

    this.loading.set(true);
    const raw = this.form.getRawValue();
    const email = String(raw.email ?? '').trim();
    const password = String(raw.password ?? '');

    try {
      const resp = await this.api.login(email, password);
      if (!resp.ok) {
        this.errorMsg.set(resp.error.message || 'Login failed');
        return;
      }

      try {
        await firstValueFrom(this.api.clockIn());
      } catch {
        // Do not fail the login if clockIn fails
      }

      this.snack.open(`Welcome, ${resp.data.name}!`, 'OK', { duration: 2000 });
      const redirect = this.route.snapshot.queryParamMap.get('redirect');
      await this.router.navigateByUrl(redirect || '/');
    } catch {
      this.errorMsg.set('Network error. Please try again.');
    } finally {
      this.loading.set(false);
    }
  }

}