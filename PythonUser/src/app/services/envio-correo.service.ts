import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NavController } from '@ionic/angular';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class EnvioCorreoService {
  private apiUrl = environment.correoApiUrl; // URL de tu API
  public currentUser: any= null; 

  constructor(private http: HttpClient, private navCtrl: NavController) { } 
 // Envía el código de verificación al correo del usuario
 sendVerificationCode(email: string): Observable<any> {
    const body = { action: 'send-code', email };
    return this.http.post(`${this.apiUrl}`, body);
  }
  
  // Verifica el código de verificación
  verifyVerificationCode(code: number): Observable<any> {
    const body = { action: 'verify-code', code };
    console.log(code); 
    return this.http.post(`${this.apiUrl}`, body);
  }
   // Restablece la contraseña del usuario
   resetPassword(newPassword: string, code: string): Observable<any> {
    const body = { action: 'reset-password', password: newPassword, code };
    return this.http.post(`${this.apiUrl}`, body);
  }
}
