import { Component } from '@angular/core';
import { MenuController, NavController } from '@ionic/angular';
import { Router, NavigationEnd } from '@angular/router';
import { UserService } from './services/user.service'; // <-- Agrega esta línea

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
})
export class AppComponent {
  constructor(
    private menuCtrl: MenuController,
    private router: Router,
    private userService: UserService
  ) {
    this.setupMenu();
  }

  private setupMenu() {
    // Habilitar menú al iniciar
    this.menuCtrl.enable(true, 'main-menu');

    // Cerrar menú al navegar
    this.router.events.subscribe(event => {
      if (event instanceof NavigationEnd) {
        this.menuCtrl.close('main-menu');
      }
    });
  }

  // Método para cerrar sesión (llamado desde el ítem del menú)
  logout() {
    this.userService.logoutUser().subscribe(
      res => {
        this.userService.setCurrentUser(null);
        this.router.navigate(['/login']);
      },
      err => {
        this.userService.setCurrentUser(null);
        this.router.navigate(['/login']);
      }
    );
  }
}