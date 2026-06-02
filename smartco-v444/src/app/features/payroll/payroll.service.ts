import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { map, Observable } from 'rxjs';

export type EmployeeLite = { id: number; name: string; email: string };

export type AttendanceSummary = {
  total_days: number;
  total_hours: number;
  estimated_pay: number;
  month: number;
  year: number;
};

export type PayrollCalcInput = {
  base: number;
  overtime_hours?: number;
  overtime_rate?: number;
  allowances?: { key: string; label: string; amount: number }[];
  deductions?: { key: string; label: string; amount: number }[];
};

export type PayrollCalcResult = {
  gross: number;
  tax: number;
  otherDeductions: number;
  totalDeductions: number;
  net: number;
  items: {
    allowances: { key: string; label: string; amount: number }[];
    deductions: { key: string; label: string; amount: number }[];
    taxDetail: { bracket: string; amount: number }[];
    overtime?: { hours: number; rate: number; amount: number } | null;
  };
};

type ApiResp<T> = { ok: boolean; data: T; error?: { code?: string; message?: string } };

@Injectable({ providedIn: 'root' })
export class PayrollService {
  private http = inject(HttpClient);

  private base = '/api/payroll';
  private usersEndpoint = '/api/users/list.php';
  private attendanceEndpoint = '/api/admin/employees/attendance.php';

  listEmployees(): Observable<EmployeeLite[]> {
    const params = new HttpParams().set('role', 'employee').set('status', 'active');

    return this.http.get<any>(this.usersEndpoint, {
      params,
      withCredentials: true,
    }).pipe(
      map((j: any) => {
        const arr =
          (Array.isArray(j) ? j : null) ??
          j?.data ??
          j?.users ??
          j?.items ??
          [];

        return (arr as any[]).map((u: any) => ({
          id: Number(u.id),
          name: String(u.name ?? u.full_name ?? u.username ?? ''),
          email: String(u.email ?? ''),
        }));
      })
    );
  }

  getAttendanceSummary(employeeId: number, year: number, month: number): Observable<AttendanceSummary> {
    const params = new HttpParams()
      .set('user_id', String(employeeId))
      .set('year', String(year))
      .set('month', String(month));

    return this.http.get<ApiResp<{ summary: AttendanceSummary }>>(this.attendanceEndpoint, {
      params,
      withCredentials: true,
    }).pipe(
      map((j) => {
        if (!j.ok) throw new Error(j.error?.message || 'Failed to load attendance');
        return j.data.summary;
      })
    );
  }

  calculate(input: PayrollCalcInput): Observable<PayrollCalcResult> {
    return this.http.post<ApiResp<PayrollCalcResult>>(`${this.base}/calc.php`, input, {
      withCredentials: true,
    }).pipe(
      map((j) => {
        if (j?.ok === false) throw new Error(j?.error?.message || 'Calc error');
        return j.data;
      })
    );
  }

  save(payload: {
    employee_id: number;
    period_year: number;
    period_month: number;
    result: PayrollCalcResult;
  }): Observable<boolean> {
    return this.http.post<ApiResp<{ id: number }>>(`${this.base}/save.php`, payload, {
      withCredentials: true,
    }).pipe(
      map((j) => !!(j?.ok || j?.data?.id))
    );
  }

  payslipUrl(employeeId: number, year: number, month: number): string {
    return `/api/payroll/payslip.php?user_id=${employeeId}&year=${year}&month=${month}`;
  }
}
