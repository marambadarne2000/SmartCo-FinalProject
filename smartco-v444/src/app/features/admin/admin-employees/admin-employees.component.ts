import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { ApiService } from '../../../core/api.service';
import { FormsModule } from '@angular/forms';


// This component is for demonstration purposes and may not cover all edge cases or best practices for a production app.
type EmployeeRow = {
  id: number;
  name: string;
  email: string;
  status: string;
  role_slug: string;
  role_name: string;
  hourly_rate: number | string;
  max_active_tasks: number | string;
  active_tasks: number;
  hours_month: number;
  month: number;
  year: number;
};


// Note: in a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of using alert()
@Component({
  selector: 'app-admin-employees',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    MatTableModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatProgressSpinnerModule
  ],
  templateUrl: './admin-employees.component.html',
  styleUrls: ['./admin-employees.component.scss']
})

// This component is for demonstration purposes and may not cover all edge cases or best practices for a production app.
export class AdminEmployeesComponent implements OnInit {
  private api = inject(ApiService);

  loading = true;
  rows: EmployeeRow[] = [];

  displayedColumns = [
    'id','name','email','role','hourly_rate','max_active_tasks',
    'active_tasks','hours_month','est_pay','payslip','actions'
  ];

  year = new Date().getFullYear();
  month = new Date().getMonth() + 1;

  saving = new Set<number>();

  ngOnInit() {
    this.reload();
  }

  // In a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of using alert()
  reload() {
    this.month = Math.min(12, Math.max(1, Number(this.month) || 1));
    this.year  = Number(this.year) || new Date().getFullYear();

    this.loading = true;
    this.api.adminListEmployees({ year: this.year, month: this.month }).subscribe({
      next: (res) => {
        this.rows = res.ok ? (res.data as EmployeeRow[]) : [];
        this.loading = false;
      },
      error: () => { this.rows = []; this.loading = false; }
    });
  }

  // In a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of using alert()
  async saveRow(r: EmployeeRow) {
    this.saving.add(r.id);
    try {
      await this.api.adminUpdateEmployeeSettings({
        user_id: r.id,
        hourly_rate: this.toNumber(r.hourly_rate),
        max_active_tasks: this.toNumber(r.max_active_tasks)
      });
    } catch (e: any) {
      alert(e?.error?.error?.message || 'Failed to save');
    } finally {
      this.saving.delete(r.id);
    }
  }


  // In a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of using alert()
  private toNumber(v: any): number {
    const n = Number(v);
    return isNaN(n) ? 0 : n;
  }


  // In a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of using alert()
  calcPay(r: EmployeeRow): number {
    const val = this.toNumber(r.hourly_rate) * this.toNumber(r.hours_month);
    return Math.round(val * 100) / 100;
  }


  // In a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of using alert()
  totalMonthPay(): number {
    const sum = (this.rows || []).reduce((acc, row) => acc + this.calcPay(row), 0);
    return Math.round(sum * 100) / 100;
  }


  // In a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of using alert()
  payslipUrl(r: EmployeeRow): string {
    return `/api/payroll/payslip.php?user_id=${r.id}&year=${this.year}&month=${this.month}`;
  }


  
  openPayslip(r: EmployeeRow) { 
    window.open(this.payslipUrl(r), '_blank', 'noopener');
  }
}
