import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

import axios from 'axios';

@Injectable({
  providedIn: 'root'
})
export class FlaskApiService {
  private baseUrl = environment.flaskUrl; // URL de tu API Flask

  constructor(private http: HttpClient) {}

  async generarQR(): Promise<string> {
    try {
      const response = await axios.get(`${this.baseUrl}/generar_qr`, { responseType: 'json' });
      if (response.data && response.data.qr_image_url) {
        return response.data.qr_image_url;}else {
          throw new Error('Error al generar el QR');}
    } catch (error) {
      console.error('Error generando QR:', error);
      throw error;
    }
  }

  async verifyQR() {
    try {
      const response = await axios.post(`${this.baseUrl}/verify_qr`);
      return response.data;
    } catch (error) {
      console.error('Error verificando QR:', error);
      throw error;
    }
  }

  reporte(id: number, nombre: string, correo: string, modo: string, correoA: string): Observable<any> {
    return new Observable(observer => {
      axios.post(`${this.baseUrl}/reporte`, { id, nombre, correo, modo, correoA})
        .then(response => {
          observer.next(response.data);
          observer.complete();
        })
        .catch(error => {
          console.error('Error en el reporte:', error);
          observer.error(error);
        });
    });
  }

  storeTempUser(user_id: number, nombre: string, correo: string, modo: string, correoA: string): Observable<any> {
    return new Observable(observer => {
      axios.post(`${this.baseUrl}/login_user`, { id: user_id, nombre, correo, modo, correoA })
        .then(response => {
          observer.next(response.data);
          observer.complete();
        })
        .catch(error => {
          console.error('Error storing temp user:', error);
          observer.error(error);
        });
    });
  }

  
  }

  


