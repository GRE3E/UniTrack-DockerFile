import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class FlaskService {
  private baseUrl = environment.flaskUrl;

  constructor(private http: HttpClient) {}

  verifyQR(contenidoQR: string): Observable<any> {
  return this.http.post<any>(`${this.baseUrl}/verify_qr`, { contenido_qr: contenidoQR });
  }

  generarQR(): Observable<any> {
    return this.http.get<any>(`${this.baseUrl}/generar_qr`);
  }
}
