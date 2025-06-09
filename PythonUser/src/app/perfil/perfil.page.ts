import { Component, OnInit } from '@angular/core';
import { UserService } from '../services/user.service';

@Component({
  selector: 'app-perfil',
  templateUrl: './perfil.page.html',
  styleUrls: ['./perfil.page.scss'],
})
export class PerfilPage implements OnInit {
  
  nombrecompleto: string = '';
  codigo_estudiante: string = ''; 
  correoA: string = ''; 
  carreraE: string= ''; 
  cicloE: string = ''; 
  edadE: string= ''; 
  sexoE: string = ''; 
  correoE: string = ''; 
  currentUser: any; 
  
  constructor(private userService: UserService) {}
 
  ngOnInit() {

    
      this.currentUser = this.userService.getCurrentUser();
      console.log('Current User en ngOnInit:', this.currentUser);
    
      if (this.currentUser && this.currentUser.user && this.currentUser.user.nombres && this.currentUser.user.apellidos && this.currentUser.user.codigo_estudiante && this.currentUser.user.correoA) {
        this.nombrecompleto = `${this.currentUser.user.nombres} ${this.currentUser.user.apellidos}`;
        this.codigo_estudiante = this.currentUser.user.codigo_estudiante;
        this.correoA = this.currentUser.user.correoA; 
        this.carreraE = this.currentUser.user.carrera;
        this.cicloE = this.currentUser.user.ciclo;
        this.edadE = this.currentUser.user.edad;
        this.sexoE = this.currentUser.user.sexo;
        this.correoE = this.currentUser.user.correo;
      } else {
        console.error('Propiedades de usuario no están definidas.');
      }
      
      console.log('Nombre completo:', this.nombrecompleto);
      console.log('Código:', this.codigo_estudiante);
      console.log('Correo:', this.correoE);
      console.log('Edad:', this.edadE);
      console.log('Sexo:', this.sexoE);
    }
  }

