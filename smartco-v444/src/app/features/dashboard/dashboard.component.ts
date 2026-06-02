// src/app/features/dashboard/dashboard.component.ts
import {
  Component,
  ViewChild,
  ElementRef,
  OnInit,
  OnDestroy,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  NgZone,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { ApiService } from '../../core/api.service';
import { Chart, ChartConfiguration } from 'chart.js/auto';
import { firstValueFrom } from 'rxjs';

type StatItem = { icon: string; label: string; value: number };
type ByStatus = { label: string; count: number };
type PerProject = { id: number; project: string; cnt: number };

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, MatCardModule, MatIconModule],
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DashboardComponent implements OnInit, OnDestroy {
  loading = true;
  error: string | null = null;

  stats: StatItem[] = [];

  private statusData: ByStatus[] = [];
  private perProjectData: PerProject[] = [];
  private priorityData: ByStatus[] = [];

  @ViewChild('doughnutRef') doughnutRef?: ElementRef<HTMLCanvasElement>;
  @ViewChild('barRef')      barRef?: ElementRef<HTMLCanvasElement>;
  @ViewChild('pieRef')      pieRef?: ElementRef<HTMLCanvasElement>;

  private doughnutChart?: Chart;
  private barChart?: Chart;
  private pieChart?: Chart;

  constructor(
    private api: ApiService,
    private cdr: ChangeDetectorRef,
    private zone: NgZone
  ) {}

  async ngOnInit(): Promise<void> {
    try {
      // 1) Overview
      const overviewResp = await firstValueFrom(this.api.getDashboardOverview());
      if (!overviewResp.ok) throw new Error(overviewResp.error.message);

      const o = overviewResp.data;
      this.stats = [
        { icon: 'workspaces',        label: 'Projects',     value: o.projects },
        { icon: 'checklist',         label: 'Tasks',        value: o.tasks },
        { icon: 'done_all',          label: 'Done',         value: o.done },
        { icon: 'progress_activity', label: 'In Progress',  value: o.in_progress },
        { icon: 'task_alt',          label: 'To Do',        value: o.todo },
        { icon: 'schedule',          label: 'Overdue',      value: o.overdue },
      ];

      // 2) Other reports in parallel
      const [byStatusResp, perProjectResp, byPriorityResp] = await Promise.all([
        firstValueFrom(this.api.getTasksByStatus()),
        firstValueFrom(this.api.getTasksPerProject(10)),
        firstValueFrom(this.api.getTasksByPriority()),
      ]);

      if (!byStatusResp.ok)   throw new Error(byStatusResp.error.message);
      if (!perProjectResp.ok) throw new Error(perProjectResp.error.message);
      if (!byPriorityResp.ok) throw new Error(byPriorityResp.error.message);

      this.statusData     = byStatusResp.data;
      this.perProjectData = perProjectResp.data;
      this.priorityData   = byPriorityResp.data;

      // 3) Activate display to create canvas elements
      this.loading = false;
      this.cdr.markForCheck();

      // 4) Draw after two animation frames to ensure DOM elements are ready
      requestAnimationFrame(() => {
        requestAnimationFrame(() => this.initChartsSafely());
      });
    } catch (e: any) {
      this.error = e?.message || 'Failed to load dashboard';
      this.loading = false;
      this.cdr.markForCheck();
    }
  }

  ngOnDestroy(): void {
    this.destroyCharts();
  }

  // ====== Charts ======
  private initChartsSafely(): void {
    this.zone.runOutsideAngular(() => {
      this.renderDoughnut(this.statusData);
      this.renderBar(this.perProjectData);
      this.renderPie(this.priorityData);

      // Re-measure after insertion
      setTimeout(() => {
        this.doughnutChart?.resize();
        this.barChart?.resize();
        this.pieChart?.resize();
      }, 0);
    });
    this.cdr.markForCheck();
  }

  private destroyCharts(): void {
    this.doughnutChart?.destroy();
    this.barChart?.destroy();
    this.pieChart?.destroy();
  }

  private renderDoughnut(rows: ByStatus[]): void {
    this.doughnutChart?.destroy();
    const el = this.doughnutRef?.nativeElement;
    if (!el) return;

    el.style.height = '300px'; // Ensure height
    const ctx = el.getContext('2d');
    if (!ctx) return;

    const labels = rows.map(r => r.label);
    const data   = rows.map(r => r.count);

    const cfg: ChartConfiguration<'doughnut'> = {
      type: 'doughnut',
      data: { labels, datasets: [{ data }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
      },
    };
    this.doughnutChart = new Chart(ctx, cfg);
  }

  private renderBar(rows: PerProject[]): void {
    this.barChart?.destroy();
    const el = this.barRef?.nativeElement;
    if (!el) return;

    el.style.height = '300px';
    const ctx = el.getContext('2d');
    if (!ctx) return;

    const labels = rows.map(r => r.project);
    const data   = rows.map(r => r.cnt);

    const cfg: ChartConfiguration<'bar'> = {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Tasks', data }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { ticks: { maxRotation: 0, autoSkip: true } },
          y: { beginAtZero: true, ticks: { precision: 0 } },
        },
        plugins: { legend: { display: false } },
      },
    };
    this.barChart = new Chart(ctx, cfg);
  }

  private renderPie(rows: ByStatus[]): void {
    this.pieChart?.destroy();
    const el = this.pieRef?.nativeElement;
    if (!el) return;

    el.style.height = '300px';
    const ctx = el.getContext('2d');
    if (!ctx) return;

    const labels = rows.map(r => r.label);
    const data   = rows.map(r => r.count);

    const cfg: ChartConfiguration<'pie'> = {
      type: 'pie',
      data: { labels, datasets: [{ data }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
      },
    };
    this.pieChart = new Chart(ctx, cfg);
  }
}