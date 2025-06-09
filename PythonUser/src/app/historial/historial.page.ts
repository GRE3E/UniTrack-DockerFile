import { Component, OnInit } from '@angular/core';
import { FlaskApiService } from '../services/flask-api.services.service';
import { UserService } from '../services/user.service';


@Component({
  selector: 'app-historial',
  templateUrl: './historial.page.html',
  styleUrls: ['./historial.page.scss'],
})
export class HistorialPage implements OnInit {
  reportes: any[] = [];
  nombrecompleto: string = ''; 
  id: number  | null = null; 
  constructor(private userService: UserService, private flaskservice: FlaskApiService) {
    console.log('Constructor de HistorialPage ejecutado');
   }

   ngOnInit() {
    
    console.log('HistorialPage initialized');
    const currentUser = this.userService.getCurrentUser();
    console.log('aqui:', currentUser);

    if (currentUser && currentUser.user) {
      this.id = currentUser.user.idUsuario;
      console.log('ID de usuario:', this.id);
      this.nombrecompleto = `${currentUser.user.nombres} ${currentUser.user.apellidos}`;
      this.obtenerReportes();
    } else {
      console.error('Propiedades de usuario no están definidas.');
    }
  }

  obtenerReportes() {
    if (this.id !== null && this.id !== undefined) {
      this.userService.gethistorial(this.id)
        .subscribe(
          (data) => {
            console.log('Datos recibidos:', data);
            this.reportes = data; // Asignar los datos de los reportes a la variable reportes
            console.log('Reportes obtenidos:', this.reportes);
          },
          (error) => {
            console.error('Error al obtener reportes:', error);
          }
        );
    } else {
      console.error('ID de usuario no válido');
    }
  }

}
