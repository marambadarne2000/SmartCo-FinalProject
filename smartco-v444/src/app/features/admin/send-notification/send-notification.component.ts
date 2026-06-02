import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { ApiService } from '../../../core/api.service';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';

@Component({
  selector: 'app-send-notification',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, MatCardModule, MatFormFieldModule,
    MatInputModule, MatSelectModule, MatButtonModule, MatSnackBarModule
  ],
  templateUrl: './send-notification.component.html',
  styleUrls: ['./send-notification.component.scss']
})
export class SendNotificationComponent {
  private api = inject(ApiService);
  private fb = inject(FormBuilder);
  private snack = inject(MatSnackBar);

  form = this.fb.group({
    title: ['', Validators.required],
    message: ['', Validators.required],
    link: [''],
    role: ['all', Validators.required] // admin, manager, all
  });

  loading = false;

submit() {
  if (this.form.invalid) {
    this.snack.open('Please fill in all required fields', 'Close', { duration: 3000 });
    return;
  }

  this.loading = true;
  const body = this.form.value as {
    title: string;
    message: string;
    link?: string;
    role: 'all' | 'admin' | 'manager';
  };

  this.api.sendNotification(body)
    .then(res => {
      this.loading = false;
      if (res.ok) {
        this.snack.open(`Notification sent to ${res.data.sent_to} users`, 'Close', { duration: 3000 });
        this.form.reset({ role: 'all' });
      } else {
        this.snack.open(res.error?.message || 'Error sending notification', 'Close', { duration: 3000 });
      }
    })
    .catch(() => {
      this.loading = false;
      this.snack.open('Network error', 'Close', { duration: 3000 });
    });
}

}
