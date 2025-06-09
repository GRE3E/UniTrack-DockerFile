import { Component, OnInit } from '@angular/core';
import { UserService } from '../services/user.service';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular';

@Component({
  selector: 'app-login',
  templateUrl: './login.page.html',
  styleUrls: ['./login.page.scss'],
})
export class LoginPage implements OnInit {

 correo: string=''; 
 contrasena: string =''; 
 showPassword: boolean = false;
 isSubmitting = false; // Para prevenir envío doble

  constructor(
    private userService: UserService, 
    private router: Router,
    private toastController: ToastController
  ) { }

  async presentToast(message: string, color: string = 'danger') {
    const toast = await this.toastController.create({
      message,
      duration: 2000,
      color,
      position: 'bottom'
    });
    toast.present();
  }

  login() {

    if (this.isSubmitting) return;
    this.isSubmitting = true;

    // Validar campos requeridos
    if (!this.correo || !this.contrasena) {
      this.presentToast('Correo y contraseña son obligatorios');
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

    // Validar longitud máxima
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
    
    this.userService.loginUser(this.correo, this.contrasena).subscribe(
      response => {
        if (response.error) {
          this.presentToast(response.message || 'Error de autenticación');
        } else {
          this.userService.setCurrentUser(response);
          this.presentToast('¡Login exitoso!', 'success');
          this.router.navigate(['/home']);
        }
        this.isSubmitting = false;
      },
      error => {
        this.presentToast(error.message || 'Error de conexión');
        this.isSubmitting = false;
      }
    );
  }

  ngOnInit() { 
    this.correo = '';
    this.contrasena = '';
    localStorage.removeItem('correo');
    localStorage.removeItem('contrasena');
   }

   ionViewWillEnter() {
    this.correo = '';
    this.contrasena = '';
    localStorage.removeItem('correo');
    localStorage.removeItem('contrasena');
  }
  
  togglePassword() {
    this.showPassword = !this.showPassword;
  }

}
