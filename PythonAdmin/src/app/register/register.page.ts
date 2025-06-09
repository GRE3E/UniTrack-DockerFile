import { Component, OnInit } from '@angular/core';
import { UserService } from '../services/user.service';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular';

@Component({
  selector: 'app-register',
  templateUrl: './register.page.html',
  styleUrls: ['./register.page.scss'],
})
export class RegisterPage implements OnInit {

  nombres: string = '';
  apellidos: string = '';
  correo: string = '';
  codigo_admin: string = '';
  edad: string= ''; 
  sexo: string= ''; 
  contrasena: string = '';
  isSubmitting = false; // Para prevenir envío doble

  constructor(
    private userService: UserService,
    private router: Router,
    private toastController: ToastController
  ) { }

  onSubmit() {
  if (this.isSubmitting) return; // Previene doble envío
  this.isSubmitting = true;

  // Validaciones básicas
  if (
    !this.nombres ||
    !this.apellidos ||
    !this.correo ||
    !this.codigo_admin ||
    !this.contrasena ||
    !this.edad ||
    !this.sexo
  ) {
    this.presentToast('Todos los campos son obligatorios');
    this.isSubmitting = false;
    return;
  }
  
  // Validar formato de correo institucional
  const correoRegex = /^[a-zA-Z0-9._%+-]+@ucvvirtual\.edu\.pe$/;
  if (!correoRegex.test(this.correo)) {
    this.presentToast('El correo debe ser institucional y válido');
    this.isSubmitting = false;
    return;
  }

  // Validar longitud máxima de campos
  if (this.nombres.length > 50 || this.apellidos.length > 50) {
    this.presentToast('Nombre y apellido no deben superar 50 caracteres');
    this.isSubmitting = false;
    return;
  }
  if (this.codigo_admin.length > 20) {
    this.presentToast('El código de administrador no debe superar 20 caracteres');
    this.isSubmitting = false;
    return;
  }
  if (this.correo.length > 100) {
    this.presentToast('El correo no debe superar 100 caracteres');
    this.isSubmitting = false;
    return;
  }
  if (this.contrasena.length < 6) {
    this.presentToast('La contraseña debe tener al menos 6 caracteres');
    this.isSubmitting = false;
    return;
  }
  if (this.contrasena.length > 50) {
    this.presentToast('La contraseña no debe superar 50 caracteres');
    this.isSubmitting = false;
    return;
  }

  // Si todo está bien, llama al servicio
  this.userService.createAdmin(
    this.nombres,
    this.apellidos,
    this.correo,
    this.codigo_admin,
    this.contrasena,
    this.edad,
    this.sexo
  ).subscribe(
    () => {
      this.presentToast('Administrador registrado con éxito', 'success');
      this.router.navigate(['/login']);
      this.isSubmitting = false;
    },
    (error: any) => {
      this.presentToast(error.message || 'Error al registrar el usuario');
      this.isSubmitting = false;
    }
  );
}

  async presentToast(message: string, color: string = 'danger') {
    const toast = await this.toastController.create({
      message: message,
      duration: 2000,
      color,
      position: 'bottom'
    });
    await toast.present();
  }
  ngOnInit() {
  }
  

}
