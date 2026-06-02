import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import {
  AdminEmployeeAttendanceRow,
  AdminEmployeeAttendanceSummary,
  AdminEmployeeDetails,
  ApiService,
} from '../../../core/api.service';

@Component({
  selector: 'app-employee-details',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './employee-details.component.html',
  styleUrls: ['./employee-details.component.scss']
})
export class EmployeeDetailsComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private api = inject(ApiService);

  employeeId: number | null = null;
  loading = false;
  loadingDetails = false;
  loadingAttendance = false;
  backendAttendanceReady = true;

  year = new Date().getFullYear();
  month = new Date().getMonth() + 1;

  details: AdminEmployeeDetails | null = null;
  attendanceRows: AdminEmployeeAttendanceRow[] = [];
  summary: AdminEmployeeAttendanceSummary | null = null;

  ngOnInit(): void {
    this.employeeId = Number(this.route.snapshot.paramMap.get('id')) || null;
    if (!this.employeeId) return;
    this.loadDetails(this.employeeId);
    this.reloadAttendance();
  }

  reloadAttendance(): void {
    if (!this.employeeId) return;
    this.month = Math.min(12, Math.max(1, Number(this.month) || 1));
    this.year = Number(this.year) || new Date().getFullYear();
    this.loadAttendance(this.employeeId, this.year, this.month);
  }

  estimatedPay(): number {
    if (this.summary) return this.summary.estimated_pay;
    const hourly = Number(this.details?.hourly_rate ?? 0);
    const hours = this.totalHours();
    return Math.round(hourly * hours * 100) / 100;
  }

  totalHours(): number {
    if (this.summary) return Number(this.summary.total_hours || 0);
    return this.attendanceRows.reduce((sum, row) => sum + Number(row.hours || 0), 0);
  }

  totalDays(): number {
    if (this.summary) return Number(this.summary.total_days || 0);
    return this.attendanceRows.filter(r => Number(r.hours || 0) > 0).length;
  }


  //loads employee details from the backend. In a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of just trying to load from the list endpoint as a fallback.
  private loadDetails(employeeId: number): void {
    this.loading = true;
    this.loadingDetails = true;
    this.api.adminGetEmployeeDetails(employeeId).subscribe({
      next: (res) => {
        if (res.ok) {
          this.details = res.data;
        }
        this.loadingDetails = false;
        this.loading = this.loadingAttendance;
      },
      error: () => {
        this.api.adminListEmployees({ year: this.year, month: this.month }).subscribe({
          next: (res) => {
            if (res.ok) {
              const fallback = (res.data || []).find((row: any) => Number(row.id) === employeeId);
              if (fallback) {
                this.details = {
                  id: fallback.id,
                  name: fallback.name,
                  email: fallback.email,
                  status: fallback.status,
                  role_slug: fallback.role_slug,
                  role_name: fallback.role_name,
                  hourly_rate: Number(fallback.hourly_rate || 0),
                  max_active_tasks: Number(fallback.max_active_tasks || 0),
                  started_at: null,
                  ended_at: null
                };
              }
            }
            this.loadingDetails = false;
            this.loading = this.loadingAttendance;
          },
          error: () => {
            this.loadingDetails = false;
            this.loading = this.loadingAttendance;
          }
        });
      }
    });
  }


  //loads attendance data for the employee for the specified month and year. In a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of just setting backendAttendanceReady to false.
  private loadAttendance(employeeId: number, year: number, month: number): void {
    this.loading = true;
    this.loadingAttendance = true;
    this.backendAttendanceReady = true;
    this.api.adminGetEmployeeAttendance({ user_id: employeeId, year, month }).subscribe({
      next: (res) => {
        if (res.ok) {
          this.attendanceRows = Array.isArray(res.data.rows) ? res.data.rows : [];
          this.summary = res.data.summary ?? null;
        } else {
          this.attendanceRows = [];
          this.summary = null;
        }
        this.loadingAttendance = false;
        this.loading = this.loadingDetails;
      },

      // Attendance endpoints are implemented in backend phase, so we can get errors until then. In a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of just setting backendAttendanceReady to false.
      error: () => {
        // Attendance endpoints are implemented in backend phase.
        this.backendAttendanceReady = false;
        this.attendanceRows = [];
        this.summary = null;
        this.loadingAttendance = false;
        this.loading = this.loadingDetails;
      }
    });
  }
}