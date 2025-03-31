import { enableProdMode, importProvidersFrom } from '@angular/core';
import { bootstrapApplication } from '@angular/platform-browser';
import { AppComponent } from './app/app.component';
import { AppRouting } from './app/app.routes';
import { BrowserModule } from '@angular/platform-browser';
import { environment } from './environments/environment';
import { provideHttpClient } from '@angular/common/http';
import { GoogleLoginProvider, SocialAuthServiceConfig, SocialLoginModule } from '@abacritt/angularx-social-login';

if (environment.production) {
  enableProdMode();
}

bootstrapApplication(AppComponent, {
  providers: [
    importProvidersFrom(BrowserModule, AppRouting, SocialLoginModule),
    provideHttpClient(),
    {
      provide: 'SocialAuthServiceConfig',
      useValue: {
        autoLogin: false,
        providers: [
          {
            id: GoogleLoginProvider.PROVIDER_ID,
            provider: new GoogleLoginProvider('1083537813059-sf3otf7eun5d7bmkh9qffmtv0t6vbgch.apps.googleusercontent.com'),
          },
        ],
        onError: (err) => console.error(err),
      } as SocialAuthServiceConfig,
    }
  ],
}).catch(err => console.error(err));
