import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatSelectModule } from '@angular/material/select';
import { MatChipsModule } from '@angular/material/chips';
import { ApiService } from '../../../core/api.service';

@Component({
  selector: 'app-project-create',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, MatDialogModule,
    MatFormFieldModule, MatInputModule, MatButtonModule,
    MatDatepickerModule, MatNativeDateModule,
    MatSelectModule, MatChipsModule
  ],
  templateUrl: './create.component.html',
  styleUrls: ['./create.component.scss']
})
export class CreateComponent implements OnInit {
  private fb = inject(FormBuilder);
  private api = inject(ApiService);
  ref = inject(MatDialogRef<CreateComponent>);

  users = signal<any[]>([]);
  loading = signal<boolean>(false);

  form = this.fb.group({
    name: ['', [Validators.required, Validators.minLength(3)]],
    description: [''],
    due_date: [null as Date | null],
    members: [[] as number[]] // مصفوفة IDs
  });

  ngOnInit() {
    this.api.listUsers().subscribe({
      next: (r: any) => this.users.set(r?.data ?? []),
      error: () => this.users.set([])
    });
  }

  /** تحويل تاريخ JS إلى YYYY-MM-DD (أو إرجاع '' عند عدم التحديد) */
  private toSqlDate(d: Date | null): string {
    if (!d) return '';
    const y = d.getFullYear();
    const m = (d.getMonth() + 1).toString().padStart(2, '0');
    const day = d.getDate().toString().padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  async save() {
    if (this.form.invalid || this.loading()) return;

    // تحقق من التاريخ (لا ماضٍ)
    const due = this.form.value.due_date as Date | null;
    if (due) {
      const today = new Date(); today.setHours(0, 0, 0, 0);
      const d = new Date(due); d.setHours(0, 0, 0, 0);
      if (d < today) { alert('Due date cannot be in the past'); return; }
    }

    // تحقق: عدد الأعضاء ≤ 10 (وتحويلهم لأرقام)
    const members = (this.form.value.members ?? []).map((x: any) => Number(x));
    if (members.length > 10) { alert('Members limit is 10'); return; }

    this.loading.set(true);
    try {
      const v: any = this.form.value;
      const payload = {
        name: v.name,
        description: v.description ?? '',
        due_date: this.toSqlDate(due), // <-- صيغة متوافقة مع الباك
        members
      };

      await this.api.createProject(payload);
      this.ref.close(true);
    } catch (e: any) {
      // عرض سبب الخطأ القادم من السيرفر إن وُجد
      alert(e?.error?.error?.message || 'Failed to create project');
    } finally {
      this.loading.set(false);
    }
  }

  cancel() { this.ref.close(false); }
}
