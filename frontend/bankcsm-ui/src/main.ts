import { bootstrapApplication } from '@angular/platform-browser';
import { AppComponent } from './app/app.component'; // Import AppComponent directly

bootstrapApplication(AppComponent).catch(err => console.error(err));