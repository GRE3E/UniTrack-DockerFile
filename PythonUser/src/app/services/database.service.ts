import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class UserService {
  private apiUrl = environment.apiUrl; // URL de tu API
  public currentUser: any = null;
  constructor(private http: HttpClient) { }

  
   // Obtener todos los usuarios
  getAllUsers(): Observable<any> {
    return this.http.get(`${this.apiUrl}`);
  }

  // Obtener un usuario por ID
  getUserById(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}?id=${id}`);
  }

  // Crear un nuevo usuario
  createUser(username: string, email: string, password: string): Observable<any> {
    const body = { action: 'create', username, email, password };
    return this.http.post(`${this.apiUrl}`, body);
  }

  // Actualizar un usuario existente
  updateUser(id: number, username: string, email: string, password: string): Observable<any> {
    const body = { action: 'update', id, username, email, password };
    return this.http.post(`${this.apiUrl}`, body);
  }

  // Eliminar un usuario
  deleteUser(id: number): Observable<any> {
    const body = { action: 'delete', id };
    return this.http.post(`${this.apiUrl}`, body);
  }

  // Iniciar sesión
  loginUser(correo: string, contrasena: string): Observable<any> {
    return this.http.post(`${this.apiUrl}`, { action: 'login', correo, contrasena });
  }

  // Gestionar el usuario actual
  setCurrentUser(user: any) {
    this.currentUser = user;
  }

  getCurrentUser() {
    return this.currentUser;
  }

}