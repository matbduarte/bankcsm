import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';

import { AppComponent } from './app.component';
import { AppRouting } from './app.routes';

@NgModule({
  declarations: [],
  imports: [BrowserModule, AppRouting, AppComponent],
  providers: [],
  bootstrap: []
})
export class AppModule {}