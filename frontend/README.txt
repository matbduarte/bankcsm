Abrir o VSCode, ir na pasta 'frontend' e rodar:
$ npm install -g @angular/cli

Depois iremos criar o projeto Angular:
$ ng new bankcsm-ui
(escolher SCSS para design e No para preprocessamento)

Abrir a pasta 'frontend/bankcsm-ui' no VSCode.

Criar a pasta 'src/environments' e criar os arquivos environment.ts e environment.prod.ts.
Adicionar os seguintes códigos em cada um deles:
/***** environment.ts start *****/
export const environment = {
    production: false,
    apiUrl: 'http://apidevmatbankcsm.soon.it/api'
};
/***** environment.ts end *****/

/***** environment.prod.ts start *****/
export const environment = {
    production: true,
    apiUrl: 'http://apidevmatbankcsm.soon.it/api'
};
/***** environment.prod.ts end *****/

Abra o arquivo angular.json e ajuste a parte de configurations > production para possuir o fileReplacements:
          "configurations": {
            "production": {
              "fileReplacements": [
                {
                  "replace": "src/environments/environment.ts",
                  "with": "src/environments/environment.prod.ts"
                }
              ],

Salve tudo e execute o seguinte cmd para criar a pasta de serviços:
$ ng generate service services/api
(será criada a pasta src/app/services/ com 2 arquivos dentro: api.service.spec.ts e api.service.ts)

Adicione o seguinte código para o arquivo api.service.ts:
/***** api.service.ts start *****/
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getCustomerAgreements() {
    return this.http.get(`${this.apiUrl}/CustomerAgreement/GetList`);
  }

  getPartyDirectory() {
    return this.http.get(`${this.apiUrl}/PartyDirectory/GetList`);
  }
}
/***** api.service.ts end *****/

Como nossa API exige um token, iremos criar um HttpInterceptor que adiciona o token automaticamente nos headers.
Para isso, execute o seguinte comando:
$ ng generate service interceptors/auth
(será criada a pasta src/app/interceptors/ com 2 arquivos dentro: auth.service.spec.ts e auth.service.ts)

Adicione o seguinte código para o arquivo auth.service.ts:
/***** auth.service.ts start *****/
import { Injectable } from '@angular/core';
import {
  HttpInterceptor,
  HttpRequest,
  HttpHandler,
  HttpEvent
} from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable()
export class AuthInterceptor implements HttpInterceptor {
  intercept(
    req: HttpRequest<any>,
    next: HttpHandler
  ): Observable<HttpEvent<any>> {
    const token = localStorage.getItem('token'); // Pegando o token armazenado

    if (token) {
      const cloned = req.clone({
        setHeaders: {
          Authorization: `Bearer ${token}`
        }
      });
      return next.handle(cloned);
    }
    return next.handle(req);
  }
}
/***** auth.service.ts end *****/

Por padrão a aplicação criada é do tipo 'standalone' mas iremos converter ela para ser baseada em módulos.
Para isso, execute o seguinte comando:
$ ng generate module app --flat
(será criado o arquivo app.module.ts)

Adicione o seguinte código nesse novo arquivo (já estamos adicionando nosso HttpInterceptor):
/***** app.module.ts start *****/
import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HttpClientModule } from '@angular/common/http';
import { HTTP_INTERCEPTORS } from '@angular/common/http';
import { AuthInterceptor } from './interceptors/auth.service';

@NgModule({
  declarations: [],
  imports: [
    BrowserModule,     // Static import for Angular modules
    HttpClientModule   // Static import for HttpClientModule
  ],
  providers: [
    { provide: HTTP_INTERCEPTORS, useClass: AuthInterceptor, multi: true }
  ],
  bootstrap: []
})
export class AppModule { }
/***** app.module.ts end *****/

Agora substitua o conteúdo do arquivo main.ts para o seguinte:
/***** main.ts start *****/
import { bootstrapApplication } from '@angular/platform-browser';
import { AppComponent } from './app/app.component'; // Import AppComponent directly

bootstrapApplication(AppComponent).catch(err => console.error(err));
/***** main.ts end *****/

Exclua o app.config.ts pois não precisamos mais dele já que mudamos nossa app para funcionar com módulos.

Abra o arquivo app.component.ts e deixe com o seguinte código:
/***** app.component.ts start *****/
import { Component } from '@angular/core';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss'],
  standalone: true
})
export class AppComponent {
  title = 'bankcsm-ui';
}
/***** app.component.ts end *****/

Abra o arquivo app.component.html e remova a última linha: <router-outlet />

Execute os seguintes comandos em ordem:
$ Remove-Item -Recurse -Force node_modules
$ Remove-Item -Force package-lock.json
$ ng cache clean
$ npm install
$ ng serve
(isso fará com que os erros do código sumam e que sua app seja iniciada)

Se ainda existirem erros no código mas que não bloqueiem a execução, tente reiniciar o VSCode para ver se somem.








Perfeito! Agora tenho um projeto em Angular com tudo configurado e meu backend preparado também.
Pra finalizar por hoje, só gostaria de subir tudo isso para meu repositório git.
O que devo fazer?

Minha estrutura de pastas é a seguinte:
- BankCSM
-- backend
--- api
---- all files to create the apis
--- sql scripts
---- all files to create the tables for backend
-- frontend
--- bankcsm-ui
---- all files we generated using ng commands in VSCode just now