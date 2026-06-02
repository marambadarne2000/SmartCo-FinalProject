import { Component, OnInit, DestroyRef, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatSelectModule } from '@angular/material/select';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { firstValueFrom } from 'rxjs';
import {
  AttendanceSummary,
  EmployeeLite,
  PayrollCalcInput,
  PayrollCalcResult,
  PayrollService
} from './payroll.service';

@Component({
  selector: 'app-payroll-page',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatSelectModule,
    MatCardModule,
    MatIconModule,
  ],
  templateUrl: './payroll-page.component.html',
  styleUrls: ['./payroll-page.component.scss']
})
export class PayrollPageComponent implements OnInit {
  private fb = inject(FormBuilder);
  private api = inject(PayrollService);
  private destroyRef = inject(DestroyRef);

  employees = signal<EmployeeLite[]>([]);
  loading = signal(false);
  loadingEmployees = signal(true);
  loadingAttendance = signal(false);
  result = signal<PayrollCalcResult | null>(null);
  attendance = signal<AttendanceSummary | null>(null);
  errorMsg = signal<string | null>(null);
  saved = signal(false);

  years = Array.from({ length: 7 }, (_, i) => new Date().getFullYear() - 3 + i);
  months = Array.from({ length: 12 }, (_, i) => i + 1);

  form = this.fb.group({
    employee_id: [null as number | null, [Validators.required]],
    period_year: [new Date().getFullYear(), [Validators.required]],
    period_month: [new Date().getMonth() + 1, [Validators.required]],

    base: [{ value: 0, disabled: true }, [Validators.required, Validators.min(0)]],
    overtime_hours: [0, [Validators.min(0)]],
    overtime_rate: [0, [Validators.min(0)]],

    allowances: this.fb.array<FormGroup>([]),
    deductions: this.fb.array<FormGroup>([]),
  });

  get allowancesFA() {
    return this.form.get('allowances') as FormArray<FormGroup>;
  }

  get deductionsFA() {
    return this.form.get('deductions') as FormArray<FormGroup>;
  }

  ngOnInit() {
    this.api.listEmployees()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (employees) => {
          this.employees.set(employees);
          this.loadingEmployees.set(false);
        },
        error: () => {
          this.employees.set([]);
          this.loadingEmployees.set(false);
          this.errorMsg.set('Failed to load employees.');
        }
      });

    this.form.get('employee_id')?.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.loadAttendanceAndBase());

    this.form.get('period_year')?.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.loadAttendanceAndBase());

    this.form.get('period_month')?.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.loadAttendanceAndBase());

    this.addAllowance('allowance_transport', 'Transport', 0);
    this.addDeduction('deduction_advance', 'Advance', 0);
  }

  addAllowance(key = '', label = '', amount = 0) {
    this.allowancesFA.push(this.fb.group({
      key: [key],
      label: [label],
      amount: [amount, [Validators.min(0)]]
    }));
  }

  removeAllowance(i: number) {
    this.allowancesFA.removeAt(i);
  }

  addDeduction(key = '', label = '', amount = 0) {
    this.deductionsFA.push(this.fb.group({
      key: [key],
      label: [label],
      amount: [amount, [Validators.min(0)]]
    }));
  }

  removeDeduction(i: number) {
    this.deductionsFA.removeAt(i);
  }

  async loadAttendanceAndBase() {
    this.errorMsg.set(null);
    this.result.set(null);
    this.saved.set(false);

    const employeeId = Number(this.form.get('employee_id')?.value || 0);
    const year = Number(this.form.get('period_year')?.value || 0);
    const month = Number(this.form.get('period_month')?.value || 0);

    if (!employeeId || !year || !month) {
      this.attendance.set(null);
      this.form.patchValue({ base: 0 }, { emitEvent: false });
      return;
    }

    this.loadingAttendance.set(true);

    try {
      const summary = await firstValueFrom(this.api.getAttendanceSummary(employeeId, year, month));
      this.attendance.set(summary);
      this.form.patchValue(
        { base: Number(summary.estimated_pay || 0) },
        { emitEvent: false }
      );
    } catch (e: any) {
      this.attendance.set(null);
      this.form.patchValue({ base: 0 }, { emitEvent: false });
      this.errorMsg.set(e?.message || 'Failed to load attendance.');
    } finally {
      this.loadingAttendance.set(false);
    }
  }

  async calculate() {
    this.errorMsg.set(null);
    this.result.set(null);
    this.saved.set(false);

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();

    const payload: PayrollCalcInput = {
      base: Number(v.base) || 0,
      overtime_hours: Number(v.overtime_hours) || 0,
      overtime_rate: Number(v.overtime_rate) || 0,
      allowances: (v.allowances || []).map((a: any) => ({
        key: String(a.key || 'allowance'),
        label: String(a.label || 'Allowance'),
        amount: Number(a.amount) || 0
      })),
      deductions: (v.deductions || []).map((d: any) => ({
        key: String(d.key || 'deduction'),
        label: String(d.label || 'Deduction'),
        amount: Number(d.amount) || 0
      })),
    };

    this.loading.set(true);

    try {
      const res = await firstValueFrom(this.api.calculate(payload));
      this.result.set(res);
    } catch (e: any) {
      this.errorMsg.set(e?.message || 'Failed to calculate.');
    } finally {
      this.loading.set(false);
    }
  }

  async save() {
    this.errorMsg.set(null);
    this.saved.set(false);

    if (!this.result()) {
      this.errorMsg.set('Calculate first.');
      return;
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();

    this.loading.set(true);

    try {
      const ok = await firstValueFrom(this.api.save({
        employee_id: Number(v.employee_id),
        period_year: Number(v.period_year),
        period_month: Number(v.period_month),
        result: this.result()!,
      }));

      if (!ok) throw new Error('Save failed.');

      this.saved.set(true);
    } catch (e: any) {
      this.errorMsg.set(e?.message || 'Failed to save.');
    } finally {
      this.loading.set(false);
    }
  }

  openPayslip() {
    const employeeId = Number(this.form.get('employee_id')?.value || 0);
    const year = Number(this.form.get('period_year')?.value || 0);
    const month = Number(this.form.get('period_month')?.value || 0);

    if (!employeeId || !year || !month) return;

    window.open(this.api.payslipUrl(employeeId, year, month), '_blank', 'noopener');
  }
}
