import { Component, inject } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSnackBarModule } from '@angular/material/snack-bar';
import { ApiService } from '../../../core/api.service';

@Component({
  selector: 'app-auth-login',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    MatCardModule, MatIconModule, MatFormFieldModule, MatInputModule,
    MatButtonModule, MatProgressSpinnerModule, MatSnackBarModule
  ],
  templateUrl: './reset-password.component.html',
  styleUrls: ['./reset-password.component.scss']
})
export class ResetPasswordComponent {
  private fb = inject(FormBuilder);
  private api = inject(ApiService);

  loading = false;

  form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
  });

  async submit() {
  this.loading = true;
  const email = this.form.get('email')?.value;

  if (this.form.invalid || !email) {
    this.loading = false;
    return;
  }

  try {
    const resp = await this.api.resetPassword(email);

    if (resp?.ok) {
      const resetUrl = (resp.data as any)?.reset_url || '';
      if (resetUrl) {
  alert('Reset link:\n' + resetUrl);
  window.open(resetUrl, '_blank');
} else {
  alert('Reset link generated successfully');
}

    } else {
      alert(resp?.error?.message || 'Failed to generate reset link');
    }
  } catch (error: any) {
    alert(error?.error?.error?.message || 'An error occurred. Please try again.');
  } finally {
    this.loading = false; 
  }
}
}

