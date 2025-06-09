
import { Component, OnInit } from '@angular/core';
import { UserService } from '../services/user.service';
import { Router } from '@angular/router';
import { response } from 'express';

@Component({
  selector: 'app-login',
  templateUrl: './login.page.html',
  styleUrls: ['./login.page.scss'],
})

export class LoginPage implements OnInit {
 correoE: string=''; 
 contrasenaE: string =''; 
  
  
 constructor(private userService: UserService, private router: Router) { }

 login() {
  this.userService.loginUser(this.correoE, this.contrasenaE).subscribe(
    response => {
      if (response.error) {
        // Manejar error de autenticación
        console.error(response.error);
      } else {
        // Manejar éxito de autenticación
        console.log('Login exitoso', response);

        // Verificar las propiedades del usuario en response
        if (response.user && response.user.nombres && response.user.apellidos && response.user.idUsuario && response.user.correo && response.user.correoA&& response.user.codigo_estudiante && response.user.carrera && response.user.ciclo && response.user.edad) {
          // Almacenar el usuario autenticado en localStorage
          localStorage.setItem('currentUser', JSON.stringify(response));
          this.userService.setCurrentUser(response);

          // Redirigir a otra interfaz
          this.router.navigate(['/perfil']);
        } else {
          console.error('Respuesta de login incompleta.', response);
        }
      }
    },
    error => {
      console.error('Error de conexión', error);
    }
  );
}

 
  
  ngOnInit() {
  }

}
