import { Injectable } from '@angular/core';
import { SocialAuthService, SocialUser } from '@abacritt/angularx-social-login';
import { BehaviorSubject, Observable } from 'rxjs';

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private userSubject = new BehaviorSubject<SocialUser | null>(null);
  user$: Observable<SocialUser | null> = this.userSubject.asObservable();

  constructor(private authService: SocialAuthService) {
    // Verifica se há um usuário salvo ao iniciar a aplicação
    const storedUser = localStorage.getItem('user');
    if (storedUser) {
      this.userSubject.next(JSON.parse(storedUser));
    }

    // Atualiza o estado quando o usuário faz login/logout
    this.authService.authState.subscribe((user) => {
      if (user) {
        localStorage.setItem('user', JSON.stringify(user)); // Salva no localStorage
        this.userSubject.next(user);
      } else {
        localStorage.removeItem('user'); // Remove ao deslogar
        this.userSubject.next(null);
      }
    });
  }

  isAuthenticated(): boolean {
    return this.userSubject.value !== null;
  }

  getUser(): SocialUser | null {
    return this.userSubject.value;
  }

  logout(): void {
    this.authService.signOut();
    localStorage.removeItem('user');
    this.userSubject.next(null);
  }
}
