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
