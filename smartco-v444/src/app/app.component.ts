import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet],   // <-- مهم
  templateUrl: './app.component.html', // أو './app.html' حسب اسم ملفك
  styleUrls: ['./app.component.scss']  // أو './app.scss'
})
export class AppComponent {
  title = 'smartco-v4'; 
}

